<?php

declare(strict_types=1);

/*
 * This file is part of the Explicit Architecture POC,
 * which is created on top of the Symfony Demo application.
 *
 * (c) Herberto Graça <herberto.graca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\App\Test\TestCase\Presentation\Web\Core\Component\Component\Blog\Admin\Post;

use Acme\App\Core\Component\Blog\Domain\Entity\Post;
use Acme\App\Core\Port\Persistence\DQL\DqlQueryBuilderInterface;
use Acme\App\Core\Port\Persistence\QueryServiceRouterInterface;
use Acme\App\Presentation\Web\Core\Port\Router\UrlGeneratorInterface;
use Acme\App\Presentation\Web\Core\Port\Router\UrlType;
use Acme\App\Test\Framework\AbstractFunctionalTest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for the controllers defined inside the PostListController used
 * for managing the blog in the backend.
 *
 * See https://symfony.com/doc/current/book/testing.html#functional-tests
 *
 * Whenever you test resources protected by a firewall, consider using the
 * technique explained in:
 * https://symfony.com/doc/current/cookbook/testing/http_authentication.html
 *
 * Execute the application tests using this command (requires PHPUnit to be installed):
 *
 *     $ cd your-symfony-project/
 *     $ ./vendor/bin/phpunit
 */
class PostControllerFunctionalTest extends AbstractFunctionalTest
{
    /**
     * @var DqlQueryBuilderInterface
     */
    private $dqlQueryBuilder;

    /**
     * @var QueryServiceRouterInterface
     */
    private $queryService;

    public function setUp(): void
    {
        $this->dqlQueryBuilder = $this->getService(DqlQueryBuilderInterface::class);
        $this->queryService = $this->getService(QueryServiceRouterInterface::class);
    }

    /**
     * @dataProvider getUrlsForRegularUsers
     */
    public function testAccessDeniedForRegularUsers($httpMethod, $url): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'john_user',
            'PHP_AUTH_PW' => 'kitten',
        ]);

        $client->request($httpMethod, $url);
        self::assertResponseStatusCode(Response::HTTP_FORBIDDEN, $client);
    }

    public function getUrlsForRegularUsers()
    {
        yield ['GET', '/en/admin/posts/1'];
        yield ['GET', '/en/admin/posts/1/edit'];
        yield ['POST', '/en/admin/posts/1'];
        yield ['POST', '/en/admin/posts/1/delete'];
    }

    public function testAdminGetPost(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'jane_admin',
            'PHP_AUTH_PW' => 'kitten',
        ]);
        $client->request('GET', '/en/admin/posts/1');

        self::assertResponseStatusCode(Response::HTTP_OK, $client);
    }

    /**
     * This test changes the database contents by editing a blog post. However,
     * thanks to the DAMADoctrineTestBundle and its PHPUnit listener, all changes
     * to the database are rolled back when this test completes. This means that
     * all the application tests begin with the same database contents.
     */
    public function testAdminEditPost(): void
    {
        $newBlogPostTitle = 'Blog Post Title ' . mt_rand();

        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'jane_admin',
            'PHP_AUTH_PW' => 'kitten',
        ]);
        $crawler = $client->request('GET', '/en/admin/posts/1/edit');
        $form = $crawler->selectButton('Save changes')->form([
            'edit_post_form[title]' => $newBlogPostTitle,
        ]);
        $client->submit($form);

        self::assertResponseStatusCode(Response::HTTP_FOUND, $client);

        /** @var Post $post */
        $post = $client->getContainer()->get('doctrine')->getRepository(Post::class)->find(1);
        $this->assertSame($newBlogPostTitle, $post->getTitle());
    }

    /**
     * This test changes the database contents by deleting a blog post. However,
     * thanks to the DAMADoctrineTestBundle and its PHPUnit listener, all changes
     * to the database are rolled back when this test completes. This means that
     * all the application tests begin with the same database contents.
     *
     * @expectedException \Acme\App\Core\Port\Persistence\Exception\EmptyQueryResultException
     */
    public function testAdminDeletePost(): void
    {
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->getService(UrlGeneratorInterface::class);

        $postId = 1;
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'jane_admin',
            'PHP_AUTH_PW' => 'kitten',
        ]);
        $client->followRedirects();
        $crawler = $client->request('GET', '/en/admin/posts/1');
        $crawler = $client->submit($crawler->filter('#delete-form')->form());

        self::assertResponseStatusCode(Response::HTTP_OK, $client);

        $this->assertSame(
            $urlGenerator->generateUrl('admin_post_list', [], UrlType::absoluteUrl()),
            $crawler->getUri()
        );

        self::assertSame(1, $crawler->filter('.alert-success')->count());
        $this->findById($postId);
    }

    public function testAdminDeletePost_with_invalid_token_does_not_delete(): void
    {
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->getService(UrlGeneratorInterface::class);

        $client = static::createClient(
            [],
            [
                'PHP_AUTH_USER' => 'jane_admin',
                'PHP_AUTH_PW' => 'kitten',
            ]
        );
        $client->followRedirects();

        $crawler = $client->request(
            'POST',
            '/en/admin/posts/1/delete',
            ['token' => 'invalid_token']
        );

        $this->assertSame(
            $urlGenerator->generateUrl('admin_post_list', [], UrlType::absoluteUrl()),
            $crawler->getUri()
        );

        self::assertSame(0, $crawler->filter('.alert-success')->count());
    }

    private function findById(int $id): Post
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->where('Post.id = :id')
            ->setParameter('id', $id)
            ->build();

        return $this->queryService->query($dqlQuery)->getSingleResult();
    }
}