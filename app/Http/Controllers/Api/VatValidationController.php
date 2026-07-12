<?php

namespace App\Http\Controllers\Api;

use App\Services\ViesResult;
use App\Services\ViesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VAT Validation API Controller
 * 
 * Provides real-time VAT number validation via EU VIES service.
 */
class VatValidationController extends ApiController
{
    public function __construct(
        private ViesService $viesService
    ) {}

    /**
     * Validate a VAT number via AJAX.
     * 
     * POST /api/validate-vat
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat_number' => 'required|string|max:20',
            'country_code' => 'nullable|string|size:2',
        ]);

        $vatNumber = strtoupper(trim($validated['vat_number']));
        
        // Extract country code from VAT number if not provided
        $countryCode = strtoupper(trim($validated['country_code'] ?? substr($vatNumber, 0, 2)));
        
        // Remove country prefix from VAT number
        if (str_starts_with($vatNumber, $countryCode)) {
            $vatNumber = substr($vatNumber, strlen($countryCode));
        }

        // Validate via VIES, unless the operator has disabled the check
        // entirely — treated the same as a temporarily-unavailable service:
        // VAT is charged normally, nothing is silently exempted.
        $result = settings('tax.vat_validation_enabled', true)
            ? $this->viesService->validate($countryCode, $vatNumber)
            : new ViesResult(valid: null, reason: 'validation_disabled', countryCode: $countryCode, vatNumber: $vatNumber);

        Log::info('VAT validation', [
            'country' => $countryCode,
            'vat' => $countryCode . $vatNumber,
            'valid' => $result->valid,
            'reason' => $result->reason,
        ]);

        return response()->json([
            'success' => true,
            'valid' => $result->valid,
            'unavailable' => $result->isUnavailable(),
            'country_code' => $result->countryCode,
            'vat_number' => $result->vatNumber,
            'company_name' => $result->name,
            'company_address' => $result->address,
            'message' => $this->getMessage($result),
        ]);
    }

    /**
     * Get user-friendly message based on validation result.
     */
    private function getMessage($result): string
    {
        if ($result->valid === true) {
            return __('VAT number is valid. VAT exemption will be applied.');
        }

        if ($result->valid === false) {
            if ($result->reason === 'not_eu') {
                return __('This VAT number is not from an EU country. VAT will be applied.');
            }
            return __('VAT number is invalid. Please check and try again. VAT will be applied.');
        }

        // Service unavailable or error
        return __('VIES service is temporarily unavailable. VAT will be applied, but you can contact us later with your valid VAT number for a refund.');
    }
}
