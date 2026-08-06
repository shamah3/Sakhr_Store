<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use LaravelAIAgent\Facades\Agent;

class AiSearchController extends BaseController
{
    /**
     * Smart Search using AI Agent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $query = $request->input('query');
        $userId = $request->user() ? $request->user()->id : 'guest_' . $request->ip();

        try {
            $response = Agent::make('SmartSearch')
                ->system('أنت محرك بحث ذكي لتطبيق "متجري". 
مهمتك هي الإجابة على استفسارات المستخدمين حول البيانات المتوفرة في التطبيق (المنتجات، الإعلانات، المتاجر، الخصومات) باستخدام الأدوات المتاحة لك.
استخدم الأدوات دائماً للبحث عن البيانات المطلوبة، ولا تقم بتأليف بيانات من عندك.
قدم الإجابة بشكل ودود، احترافي، ومرتب.')
                ->conversation('search_' . $userId) // الحفاظ على سياق المحادثة إذا لزم الأمر
                ->chat($query);

            return $this->sendResponse(['answer' => $response], 'تم إجراء البحث الذكي بنجاح');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Smart Search Error: ' . $e->getMessage());
            return $this->sendError('عذراً، حدث خطأ أثناء معالجة طلبك.', [], 500);
        }
    }
}
