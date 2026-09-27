<?php

namespace App\Filament\Resources\Instructors\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstructorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Instructor Profile')
                    ->components([
                        TextEntry::make('name')
                            ->state(fn(User $record): string => "Name: {$record->name}"),

                        TextEntry::make('email')
                            ->state(fn(User $record): string => "Email: {$record->email}"),

                        TextEntry::make('iban_account')
                            ->state(fn(User $record): string => "IBAN / Payout Account: " . ($record->iban_account ?? 'Not set')),
                    ]),

                Section::make('Real-Time Ledger Balances')
                    ->components([
                        TextEntry::make('owed_balance')
                            ->state(fn(User $record): string => "Outstanding Owed Balance: $" . number_format($record->getOwedBalanceInCents() / 100, 2)),

                        TextEntry::make('total_paid')
                            ->state(fn(User $record): string => "Total Paid Out: $" . number_format($record->getTotalPaidInCents() / 100, 2)),

                        TextEntry::make('total_earned')
                            ->state(fn(User $record): string => "Total Lifetime Earned: $" . number_format($record->getTotalEarnedInCents() / 100, 2)),
                    ]),
            ]);
    }
}
