<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Levee quand un changement de statut viole le workflow configure. */
class WorkflowException extends \RuntimeException
{
    /** @param string[] $erreurs */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(implode(' ', $erreurs));
    }
}
