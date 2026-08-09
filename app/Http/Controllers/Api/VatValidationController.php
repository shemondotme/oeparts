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
class VatValidationController extends BaseApiController
{
    public function __construct(
        private ViesService $viesService
    ) {}

    /**
     * Validate a VAT number via AJAX.
     *
     * POST /api/validate-vat
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat_number' => 'required|string|max:20',
            'country_code' => 'nullable|string|size:2',
        ]);

        $vatNumber = strtoupper(preg_replace('/[\s.\-]/', '', $validated['vat_number']));

        // A standard EU VAT number carries its own 2-letter alpha country
        // prefix (e.g. "DE123456789") — that prefix is authoritative and
        // must win over a separately-submitted country_code (e.g. from a
        // shipping-country dropdown). The previous version only stripped the
        // prefix when it happened to match the (possibly different)
        // country_code param; when they disagreed, the mismatched pair
        // ("DE", "FR123456789") was sent to VIES as-is and always failed
        // validation instead of actually checking against FR.
        if (preg_match('/^([A-Z]{2})([A-Z0-9]+)$/', $vatNumber, $matches)) {
            $countryCode = $matches[1];
            $vatNumber = $matches[2];
        } else {
            $countryCode = strtoupper(trim($validated['country_code'] ?? ''));
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
