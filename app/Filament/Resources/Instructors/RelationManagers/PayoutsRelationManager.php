<?php

namespace App\Filament\Resources\Instructors\RelationManagers;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payout History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#ID')
                    ->sortable(),

                TextColumn::make('amount_in_cents')
                    ->label('Amount')
                    ->getStateUsing(fn(Payout $record): string => '$' . number_format($record->amount_in_cents / 100, 2))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(PayoutStatus $state): string => match ($state) {
                        PayoutStatus::PAID => 'success',
                        PayoutStatus::PROCESSING => 'warning',
                        PayoutStatus::IN_DOUBT => 'gray',
                        PayoutStatus::FAILED => 'danger',
                        PayoutStatus::PENDING => 'info',
                    })
                    ->sortable(),

                TextColumn::make('external_reference')
                    ->label('Gateway Reference')
                    ->placeholder('None')
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('failure_reason')
                    ->label('Failure Reason')
                    ->placeholder('None')
                    ->wrap()
                    ->limit(50),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('Unpaid')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Initiated At')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
