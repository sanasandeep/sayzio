<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\QrArtAllowanceExceededException;
use App\Services\AI\QrArtGenerationException;
use App\Services\AI\QrArtUnavailableException;
use App\Services\AI\QrReferenceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QrReferenceController extends Controller
{
    public function estimate(Request $request, QrReferenceService $service)
    {
        return response()->json($service->estimate($request));
    }

    public function generate(Request $request, QrReferenceService $service)
    {
        $v = $request->validate(['quote' => 'required|uuid']);
        try {
            return response()->json($service->generate($v['quote']));
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => 'Not enough coins. Estimate again to see your balance.'], 402);
        } catch (QrArtGenerationException|QrArtUnavailableException|QrArtAllowanceExceededException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI QR could not finish. Any completed analysis charge has been refunded. Check the provider configuration or try again.'], 422);
        }
    }
}
