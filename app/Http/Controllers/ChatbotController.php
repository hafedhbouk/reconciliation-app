<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Point d'entrée du chatbot métier (fenêtre « Assistant » de chaque page).
 * La réponse est calculée par App\Services\Chatbot\Chatbot, avec les droits
 * de l'utilisateur connecté. La question n'est pas enregistrée.
 */
class ChatbotController extends Controller
{
    public function ask(Request $request, Chatbot $chatbot): JsonResponse
    {
        $validated = $request->validate(
            ['question' => ['required', 'string', 'max:500']],
            ['question.required' => 'Écrivez une question.', 'question.max' => 'La question ne doit pas dépasser 500 caractères.'],
        );

        return response()->json($chatbot->ask($request->user(), $validated['question'])->toArray());
    }
}
