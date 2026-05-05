<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Scalable Comment System</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Outfit', sans-serif; }
        </style>
    </head>
    <body class="bg-gray-50 text-gray-900 antialiased">
        {{-- Navigation --}}
        <nav class="bg-white border-b border-gray-200 py-4 sticky top-0 z-50">
            <div class="max-w-5xl mx-auto px-6 flex justify-between items-center">
                <a href="/" class="text-2xl font-bold tracking-tight text-primary-600">
                    <span class="text-amber-500">Scalable</span>Comments
                </a>
                <div class="flex items-center space-x-6">
                    @auth
                        <a href="/app" class="text-sm font-semibold text-gray-600 hover:text-amber-500 transition-colors">User Panel</a>
                        @if(auth()->user()->is_admin)
                            <a href="/admin" class="text-sm font-semibold text-gray-600 hover:text-amber-500 transition-colors">Admin Panel</a>
                        @endif
                    @else
                        <a href="/app/login" class="text-sm font-semibold text-gray-600 hover:text-amber-500 transition-colors">Log in</a>
                        <a href="/app/register" class="bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-amber-600 transition-all shadow-md hover:shadow-lg">Get Started</a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Hero --}}
        <header class="bg-white py-20 border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-6 text-center">
                <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 leading-tight mb-6">
                    The Future of <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-500 to-orange-600">Nested Conversations</span>.
                </h1>
                <p class="text-xl text-gray-500 max-w-2xl mx-auto mb-10">
                    A high-performance, O(1) comment architecture designed for millions of users and zero recursive queries.
                </p>
            </div>
        </header>

        {{-- Feed --}}
        <main class="max-w-3xl mx-auto px-6 py-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 flex items-center">
                <svg class="w-6 h-6 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM7 8h10M7 12h10M7 16h10"></path></svg>
                Community Feed
            </h2>

            <div class="space-y-8">
                @foreach($posts as $post)
                    <article class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:border-amber-200 transition-all group">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 font-bold">
                                {{ substr($post->user->name, 0, 1) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900">{{ $post->user->name }}</h3>
                                <p class="text-xs text-gray-400">{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                            @if($post->user->is_admin)
                                <span class="bg-amber-100 text-amber-600 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Admin</span>
                            @endif
                        </div>
                        
                        <h4 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-amber-500 transition-colors">
                            {{ $post->title }}
                        </h4>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            {{ Str::limit($post->body, 200) }}
                        </p>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-50">
                            <div class="flex items-center space-x-4 text-sm text-gray-400">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    {{ $post->comments_count ?? 0 }} comments
                                </span>
                            </div>
                            <a href="/app/posts/{{ $post->id }}/comments" class="inline-flex items-center text-amber-500 font-bold hover:text-amber-600 transition-colors">
                                Join Discussion
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-12">
                {{ $posts->links() }}
            </div>
        </main>

        <footer class="bg-white border-t border-gray-200 py-12 text-center text-gray-400 text-sm">
            &copy; {{ date('Y') }} Scalable Comment System. Built with Laravel 13 & Filament v4.
        </footer>
    </body>
</html>
