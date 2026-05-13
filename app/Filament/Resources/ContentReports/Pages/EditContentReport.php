<?php

namespace App\Filament\Resources\ContentReports\Pages;

use App\Filament\Resources\ContentReports\ContentReportResource;
use App\Models\ContentReport;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditContentReport extends EditRecord
{
    protected static string $resource = ContentReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Stamp the reviewer and review time whenever the status moves past pending.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $movingOutOfPending = ($data['status'] ?? null) !== ContentReport::STATUS_PENDING
            && $this->record->status === ContentReport::STATUS_PENDING;

        if ($movingOutOfPending) {
            $data['reviewed_by'] = Auth::id();
            $data['reviewed_at'] = now();
        }

        return $data;
    }
}
