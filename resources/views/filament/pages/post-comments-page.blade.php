<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Post Header --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ $this->post->title }}
            </x-slot>
            
            <div class="prose dark:prose-invert max-w-none">
                {{ $this->post->body }}
            </div>
        </x-filament::section>

        {{-- Add Root Comment Action --}}
        <div class="flex justify-end">
            {{ $this->addCommentAction }}
        </div>

        {{-- Comments List --}}
        <div class="space-y-4">
            @forelse ($this->getComments() as $comment)
                @include('filament.pages.partials.comment-item', ['comment' => $comment, 'depth' => 0])
            @empty
                <x-filament::section>
                    <div class="py-12 text-center text-gray-500">
                        No comments yet. Be the first to join the conversation!
                    </div>
                </x-filament::section>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div>
            {{ $this->getComments()->links() }}
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
