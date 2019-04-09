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

namespace Acme\App\Presentation\Api\GraphQl\Node\Comment\Connection\Author;

use Acme\App\Core\Component\User\Application\Repository\UserRepositoryInterface;
use Acme\App\Core\Component\User\Domain\User\User;
use Acme\App\Core\Port\Persistence\Exception\EmptyQueryResultException;
use Acme\App\Core\SharedKernel\Component\Blog\Domain\Post\Comment\CommentId;
use Acme\App\Presentation\Api\GraphQl\Node\User\AbstractUserViewModel;
use Doctrine\Common\Collections\Collection;
use Overblog\GraphQLBundle\Relay\Connection\Output\Connection;
use Overblog\GraphQLBundle\Relay\Connection\Output\ConnectionBuilder;
use function array_map;
use function count;

final class CommentAuthorsResolver
{
    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    public function __construct(
        UserRepositoryInterface $userRepository
    ) {
        $this->userRepository = $userRepository;
    }

    public function getCommentAuthorsConnection(CommentId $commentId): Connection
    {
        try {
            /** @var Collection $authorList */
            $authorList = $this->userRepository->findAllByCommentId($commentId);
        } catch (EmptyQueryResultException $e) {
            return ConnectionBuilder::connectionFromArray([]);
        }

        $authorViewModelList = array_map(
            function (User $author) {
                return AbstractUserViewModel::constructFromEntity($author);
            },
            $authorList->toArray()
        );

        return ConnectionBuilder::connectionFromArray($authorViewModelList);
    }

    public function countEdges(Connection $connection): int
    {
        return count($connection->edges);
    }
}
