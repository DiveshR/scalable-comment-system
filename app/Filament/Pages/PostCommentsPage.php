<?php

namespace App\Filament\Pages;

use App\Models\Comment;
use App\Models\Post;
use App\Services\CommentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * PostCommentsPage — A custom Filament page for the User Panel.
 */
class PostCommentsPage extends Page
{
    protected string $view = 'filament.pages.post-comments-page';

    // Route parameter: /app/posts/{post}/comments
    protected static ?string $slug = 'posts/{post}/comments';

    // Navigation configuration
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Post Comments';

    public Post $post;
    public ?int $authUserId = null;

    /**
     * Mount the page with the post model.
     */
    public function mount(Post $post): void
    {
        $this->post = $post;
        $this->authUserId = auth()->id();
    }

    /**
     * Get the comments for the current post.
     */
    public function getComments(): LengthAwarePaginator
    {
        return app(CommentService::class)->getCommentsForPost(
            postId: $this->post->id,
            authUserId: $this->authUserId,
            perPage: 10
        );
    }

    /**
     * Action to create a new top-level comment.
     */
    public function addCommentAction(): Action
    {
        return Action::make('addComment')
            ->label('Add Comment')
            ->form([
                Textarea::make('content')
                    ->placeholder('Write a comment...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data) {
                app(CommentService::class)->createComment(
                    postId: $this->post->id,
                    userId: $this->authUserId,
                    content: $data['content']
                );

                Notification::make()
                    ->success()
                    ->title('Comment posted.')
                    ->send();
            })
            ->visible(fn () => auth()->check());
    }

    /**
     * Action to reply to an existing comment.
     */
    public function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon('heroicon-m-chat-bubble-left-right')
            ->size('sm')
            ->form([
                Textarea::make('content')
                    ->placeholder('Write a reply...')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (array $data, array $arguments) {
                try {
                    app(CommentService::class)->replyToComment(
                        parentId: $arguments['commentId'],
                        userId: $this->authUserId,
                        content: $data['content']
                    );

                    Notification::make()
                        ->success()
                        ->title('Reply posted.')
                        ->send();
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn () => auth()->check());
    }

    /**
     * Action to toggle a like on a comment.
     */
    public function toggleLikeAction(): Action
    {
        return Action::make('toggleLike')
            ->label(fn (array $arguments) => $this->hasLiked($arguments['commentId']) ? 'Unlike' : 'Like')
            ->icon(fn (array $arguments) => $this->hasLiked($arguments['commentId']) ? 'heroicon-s-heart' : 'heroicon-o-heart')
            ->color(fn (array $arguments) => $this->hasLiked($arguments['commentId']) ? 'danger' : 'gray')
            ->size('sm')
            ->action(function (array $arguments) {
                app(CommentService::class)->toggleLike(
                    commentId: $arguments['commentId'],
                    userId: $this->authUserId
                );
            })
            ->visible(fn () => auth()->check());
    }

    /**
     * Action to delete a comment.
     */
    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                try {
                    app(CommentService::class)->deleteComment(
                        commentId: $arguments['commentId'],
                        userId: $this->authUserId
                    );

                    Notification::make()
                        ->success()
                        ->title('Comment deleted.')
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (array $arguments) => $arguments['authorId'] === $this->authUserId);
    }

    /**
     * Helper to check if user has liked a comment.
     */
    protected function hasLiked(int $commentId): bool
    {
        $comment = Comment::find($commentId);
        if (!$comment) return false;

        return $comment->likes->where('user_id', $this->authUserId)->isNotEmpty();
    }
}
