<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Validator;
use App\Models\Client;
use App\Models\Contact;

class ContactController extends Controller
{
    public function store(string $clientId): void
    {
        $id     = (int) $clientId;
        $client = (new Client())->find($id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $data      = $this->collect($id);
        $validator = $this->validate($data);
        if ($validator->fails()) {
            $this->backWithErrors('/clients/' . $id, $validator->errors(), $_POST);
        }

        $contacts = new Contact();
        if ((int) $data['principal'] === 1) {
            $contacts->clearPrincipal($id);
        }
        $contactId = $contacts->create($data);

        Audit::log('contact_creation', 'contact', $contactId, ['client_id' => $id]);
        flash('success', 'Contact ajoute.');
        $this->redirect('/clients/' . $id . '#contacts');
    }

    public function update(string $clientId, string $contactId): void
    {
        $contacts = new Contact();
        $contact  = $contacts->find((int) $contactId);
        if (!$contact || (int) $contact['client_id'] !== (int) $clientId) {
            $this->notFound('Contact introuvable.');
        }

        $data      = $this->collect((int) $clientId);
        $validator = $this->validate($data);
        if ($validator->fails()) {
            $this->backWithErrors('/clients/' . $clientId, $validator->errors(), $_POST);
        }

        if ((int) $data['principal'] === 1) {
            $contacts->clearPrincipal((int) $clientId, (int) $contactId);
        }
        $contacts->update((int) $contactId, $data);

        Audit::log('contact_modification', 'contact', (int) $contactId);
        flash('success', 'Contact mis a jour.');
        $this->redirect('/clients/' . $clientId . '#contacts');
    }

    public function destroy(string $clientId, string $contactId): void
    {
        $contacts = new Contact();
        $contact  = $contacts->find((int) $contactId);
        if (!$contact || (int) $contact['client_id'] !== (int) $clientId) {
            $this->notFound('Contact introuvable.');
        }

        if ((int) $contact['principal'] === 1) {
            flash('error', 'Definissez un autre contact principal avant de supprimer celui-ci.');
            $this->redirect('/clients/' . $clientId . '#contacts');
        }

        $contacts->delete((int) $contactId);
        Audit::log('contact_suppression', 'contact', (int) $contactId);
        flash('success', 'Contact supprime.');
        $this->redirect('/clients/' . $clientId . '#contacts');
    }

    private function collect(int $clientId): array
    {
        return [
            'client_id' => $clientId,
            'civilite'  => (string) $this->post('civilite', '') ?: null,
            'nom'       => (string) $this->post('nom', ''),
            'prenom'    => (string) $this->post('prenom', '') ?: null,
            'fonction'  => (string) $this->post('fonction', '') ?: null,
            'email'     => (string) $this->post('email', '') ?: null,
            'telephone' => (string) $this->post('telephone', '') ?: null,
            'mobile'    => (string) $this->post('mobile', '') ?: null,
            'type'      => (string) $this->post('type', '') ?: null,
            'principal' => (int) ($this->post('principal') !== null ? 1 : 0),
            'notes'     => (string) $this->post('notes', '') ?: null,
        ];
    }

    private function validate(array $data): Validator
    {
        return (new Validator($data, ['nom' => 'Le nom']))
            ->required('nom')->length('nom', 1, 120)
            ->email('email');
    }
}
