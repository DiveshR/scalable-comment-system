<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UserLatestCommentsWidget extends TableWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'My Recent Comments';

    public static function canView(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'app';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Comment::query()
                    ->where('user_id', auth()->id())
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('post.title')
                    ->label('Post')
                    ->limit(40),
                TextColumn::make('content')
                    ->label('Comment')
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
