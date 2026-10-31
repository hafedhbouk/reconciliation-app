{{--
    Composant <x-chatbot> : bouton flottant et fenêtre « Assistant » (chatbot métier).
    Les questions sont envoyées à ChatbotController (route chatbot.ask) ; les réponses
    sont calculées sur le serveur, avec les droits de l'utilisateur connecté.
    Comportement : resources/js/chatbot.js.
--}}
@auth
    <button type="button" class="btn btn-primary rounded-pill shadow chatbot-launcher position-fixed bottom-0 end-0 m-3 px-3"
            data-bs-toggle="offcanvas" data-bs-target="#chatbot-panel" aria-controls="chatbot-panel" aria-label="{{ __('Assistant') }}" style="z-index: 1040;">
        <i class="bi bi-chat-dots"></i><span class="d-none d-sm-inline ms-1">{{ __('Assistant') }}</span>
    </button>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="chatbot-panel" aria-labelledby="chatbot-title"
         data-chatbot data-url="{{ route('chatbot.ask') }}" data-examples="{{ json_encode(app(\App\Services\Chatbot\Chatbot::class)->examples(auth()->user())) }}">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-0" id="chatbot-title"><i class="bi bi-chat-dots me-1"></i>{{ __('Assistant') }}</h5>
                <div class="small text-secondary">{{ __('Questions sur les données que vous avez le droit de consulter.') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Fermer') }}"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="flex-grow-1 overflow-auto p-3" data-chatbot-messages aria-live="polite"></div>
            <form class="border-top p-3" data-chatbot-form>
                <label for="chatbot-question" class="visually-hidden">{{ __('Votre question') }}</label>
                <div class="input-group">
                    <input type="text" id="chatbot-question" name="question" class="form-control" maxlength="500" autocomplete="off"
                           placeholder="{{ __('Ex. : taux de rapprochement en juillet 2026') }}" required>
                    <button type="submit" class="btn btn-primary" aria-label="{{ __('Envoyer') }}"><i class="bi bi-send"></i></button>
                </div>
                <div class="form-text">{{ __('Réponses calculées sur les données réelles de l’application, sans service externe.') }}</div>
            </form>
        </div>
    </div>
@endauth
