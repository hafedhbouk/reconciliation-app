<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail des résultats d'un rapprochement : chiffres clés, lien vers
 * l'application (connexion obligatoire) et, sur demande, le fichier exporté.
 * Aucune ligne de données dans le corps du message (confidentialité).
 * Préparé et envoyé par App\Services\ResultEmails.
 */
class MatchingResultsMail extends Mailable
{
    /**
     * @param  array<string,mixed>  $summary  voir ResultEmails::summary()
     * @param  array{path:string,name:string}|null  $file
     */
    public function __construct(public array $summary, public ?array $file = null, public ?string $note = null)
    {
        // Toujours en français, même si le worker a été lancé avec une autre langue.
        $this->locale('fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Résultats du rapprochement — '.$this->summary['operation']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.matching-results');
    }

    public function attachments(): array
    {
        return $this->file === null ? [] : [Attachment::fromPath($this->file['path'])->as($this->file['name'])];
    }
}
