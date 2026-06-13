<?php

namespace App\Filament\Support;

trait DispatchesToasts
{
    public function dispatchToast(string $type, string $message, int $duration = 4000): void
    {
        $this->dispatch(
            'toast',
            type: $type,
            message: $message,
            duration: $duration,
        );
    }

    public function toastSuccess(string $message, int $duration = 4000): void
    {
        $this->dispatchToast('success', $message, $duration);
    }

    public function toastError(string $message, int $duration = 5000): void
    {
        $this->dispatchToast('error', $message, $duration);
    }

    public function toastWarning(string $message, int $duration = 4500): void
    {
        $this->dispatchToast('warning', $message, $duration);
    }

    public function toastInfo(string $message, int $duration = 4000): void
    {
        $this->dispatchToast('info', $message, $duration);
    }
}
