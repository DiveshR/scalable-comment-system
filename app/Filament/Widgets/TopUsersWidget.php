<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopUsersWidget extends TableWidget
{
    public static function canView(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin';
    }

    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top 10 Most Active Users';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('is_admin', false)
                    ->withCount('posts')
                    ->orderByDesc('posts_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(),
                TextColumn::make('posts_count')
                    ->label('Total Posts')
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Joined'),
            ])
            ->paginated(false);
    }
}
