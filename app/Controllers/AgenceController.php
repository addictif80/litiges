<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Validator;
use App\Models\Agence;
use App\Models\Litige;

class AgenceController extends Controller
{
    public function index(): void
    {
        $this->view('agences/index', [
            'titre'   => 'Agences',
            'agences' => (new Agence())->allWithStats(),
        ]);
    }

    public function store(): void
    {
        $data      = $this->collect();
        $validator = $this->validate($data, null);
        if ($validator->fails()) {
            $this->backWithErrors('/agences', $validator->errors(), $_POST);
        }

        $id = (new Agence())->create($data);
        Audit::log('agence_creation', 'agence', $id, ['nom' => $data['nom']]);

        flash('success', 'Agence creee.');
        $this->redirect('/agences');
    }

    public function update(string $id): void
    {
        $agences = new Agence();
        $agence  = $agences->find((int) $id);
        if (!$agence) {
            $this->notFound('Agence introuvable.');
        }

        $data      = $this->collect();
        $validator = $this->validate($data, (int) $id);
        if ($validator->fails()) {
            $this->backWithErrors('/agences', $validator->errors(), $_POST);
        }

        $agences->update((int) $id, $data);
        Audit::log('agence_modification', 'agence', (int) $id);

        flash('success', 'Agence mise a jour.');
        $this->redirect('/agences');
    }

    public function destroy(string $id): void
    {
        $agences = new Agence();
        $agence  = $agences->find((int) $id);
        if (!$agence) {
            $this->notFound('Agence introuvable.');
        }

        $nb = (new Litige())->count('agence_id = :a', ['a' => (int) $id]);
        if ($nb > 0) {
            flash('error', "Impossible de supprimer : {$nb} litige(s) sont rattaches a cette agence.");
            $this->redirect('/agences');
        }

        $agences->delete((int) $id);
        Audit::log('agence_suppression', 'agence', (int) $id, ['nom' => $agence['nom']]);

        flash('success', 'Agence supprimee.');
        $this->redirect('/agences');
    }

    private function collect(): array
    {
        return [
            'nom'       => (string) $this->post('nom', ''),
            'code'      => (string) $this->post('code', '') ?: null,
            'adresse'   => (string) $this->post('adresse', '') ?: null,
            'ville'     => (string) $this->post('ville', '') ?: null,
            'telephone' => (string) $this->post('telephone', '') ?: null,
            'email'     => (string) $this->post('email', '') ?: null,
            'actif'     => $this->post('actif') !== null ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $exceptId): Validator
    {
        $validator = new Validator($data, ['nom' => "Le nom de l'agence"]);
        $validator->required('nom')->length('nom', 2, 120)->email('email');

        if (!empty($data['code']) && (new Agence())->codeExists((string) $data['code'], $exceptId)) {
            $validator->custom('code', false, 'Ce code agence est deja utilise.');
        }
        return $validator;
    }
}
