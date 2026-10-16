<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Levee quand un litige semble deja exister (meme facture / meme contrat et montant). */
class DoublonException extends \RuntimeException
{
    /** @param array<int,array<string,mixed>> $doublons */
    public function __construct(public readonly array $doublons)
    {
        $references = array_map(static fn (array $d): string => (string) $d['reference'], $doublons);
        parent::__construct('Litige(s) similaire(s) déjà enregistré(s) : ' . implode(', ', $references));
    }
}
