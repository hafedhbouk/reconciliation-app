<?php

namespace App\Http\Middleware;

/**
 * Middleware de sécurité : envoie des en-têtes HTTP de protection sur
 * chaque réponse admin.
 *
 * Le Content-Security-Policy autorise 'unsafe-inline' pour les scripts
 * et les styles car les vues admin utilisent massivement des blocs
 * inline (@push, scripts de layout). Ce compromis est documenté et
 * reflète le modèle de menace d'un outil interne (pas un site public
 * acceptant du HTML utilisateur).
 */
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP allows 'unsafe-inline' for script-src/style-src -- every admin view
 * has inline @push('scripts') blocks (DataTables config, Chart.js
 * construction) and the theme-toggle script is inline in the layout.
 * A nonce-based CSP would need touching ~15+ view files for comparatively
 * little marginal gain on this app's actual threat model (a server-rendered
 * internal admin tool, not a public site taking arbitrary user HTML) --
 * documented trade-off, not an oversight. A stricter nonce-based CSP is a
 * reasonable future upgrade once/if that surface grows.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ]));
        // Harmless to always send: browsers only honor HSTS when it arrives
        // over an actual HTTPS connection, per spec.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
