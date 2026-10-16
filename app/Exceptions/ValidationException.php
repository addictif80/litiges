<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Donnees invalides : le tableau associe chaque champ a son message. */
class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $erreurs */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct((string) (reset($erreurs) ?: 'Données invalides.'));
    }
}
