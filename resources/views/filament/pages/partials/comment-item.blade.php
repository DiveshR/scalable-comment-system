@php
    $hasLiked = $comment->likes->where('user_id', auth()->id())->isNotEmpty();
    $isOwner = $comment->user_id === auth()->id();
@endphp

<div @class([
    'flex flex-col space-y-4 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm transition-all hover:shadow-md',
    'ml-0' => $depth === 0,
    'ml-8 border-l-2 border-l-primary-500' => $depth > 0,
])>
    {{-- Comment Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 font-bold text-xs">
                {{ substr($comment->user->name, 0, 1) }}
            </div>
            <div>
                <span class="font-semibold text-sm text-gray-900 dark:text-white">
                    {{ $comment->user->name }}
                </span>
                <span class="text-xs text-gray-500 ml-2">
                    {{ $comment->created_at->diffForHumans() }}
                </span>
            </div>
        </div>

        {{-- Delete Action (Only for owners) --}}
        @if($isOwner)
            <div>
                {{ ($this->deleteAction)(['commentId' => $comment->id, 'authorId' => $comment->user_id]) }}
            </div>
        @endif
    </div>

    {{-- Comment Content --}}
    <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
        @if($comment->trashed())
            <span class="italic text-gray-400">[ This comment has been deleted ]</span>
        @else
            {{ $comment->content }}
        @endif
    </div>

    {{-- Footer Actions --}}
    <div class="flex items-center space-x-4 pt-2 border-t border-gray-50 dark:border-gray-800/50">
        {{-- Like Button --}}
        <div class="flex items-center">
            {{ ($this->toggleLikeAction)(['commentId' => $comment->id]) }}
            <span class="text-xs font-medium text-gray-500 ml-1">
                {{ number_format($comment->like_count) }}
            </span>
        </div>

        {{-- Reply Button (Only if below max depth) --}}
        @if(!$comment->isMaxDepth() && !$comment->trashed())
            <div class="flex items-center">
                {{ ($this->replyAction)(['commentId' => $comment->id]) }}
                <span class="text-xs font-medium text-gray-500 ml-1">
                    {{ number_format($comment->reply_count) }}
                </span>
            </div>
        @endif
    </div>

    {{-- Nested Replies --}}
    @if($comment->replies->isNotEmpty())
        <div class="space-y-4 pt-4 mt-2">
            @foreach($comment->replies as $reply)
                @include('filament.pages.partials.comment-item', [
                    'comment' => $reply,
                    'depth' => $depth + 1
                ])
            @endforeach
        </div>
    @endif
</div>
