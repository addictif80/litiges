<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Validator;
use App\Models\ModeleCourrier;
use App\Services\CourrierService;
use App\Services\HtmlSanitizer;

/** Administration des modeles de courriers. */
class ModeleCourrierController extends Controller
{
    public function index(): void
    {
        $this->view('courriers/modeles', [
            'titre'   => 'Modèles de courriers',
            'modeles' => (new ModeleCourrier())->all('1', [], 'nom ASC'),
        ]);
    }

    public function create(): void
    {
        $this->formulaire(['actif' => 1, 'type' => 'relance'], 'creation', 'Nouveau modèle');
    }

    public function edit(string $id): void
    {
        $modele = (new ModeleCourrier())->find((int) $id);
        if (!$modele) {
            $this->notFound('Modèle introuvable.');
        }
        $this->formulaire($modele, 'edition', 'Modifier le modèle');
    }

    public function store(): void
    {
        $data = $this->collecter();
        $modeles = new ModeleCourrier();
        $validator = $this->valider($data, null);
        if ($validator->fails()) {
            $this->backWithErrors('/parametres/modeles/nouveau', $validator->errors(), $_POST);
        }

        $id = $modeles->create($data + ['systeme' => 0]);
        Audit::log('modele_courrier_creation', 'modele_courrier', $id, ['code' => $data['code']]);
        flash('success', 'Modèle créé.');
        $this->redirect('/parametres/modeles');
    }

    public function update(string $id): void
    {
        $modeles = new ModeleCourrier();
        $modele  = $modeles->find((int) $id);
        if (!$modele) {
            $this->notFound('Modèle introuvable.');
        }

        $data = $this->collecter();
        if ((int) $modele['systeme'] === 1) {
            $data['code'] = $modele['code']; // le code d'un modele fourni est immuable
        }
        $validator = $this->valider($data, (int) $id);
        if ($validator->fails()) {
            $this->backWithErrors('/parametres/modeles/' . $id . '/modifier', $validator->errors(), $_POST);
        }

        $modeles->update((int) $id, $data);
        Audit::log('modele_courrier_modification', 'modele_courrier', (int) $id);
        flash('success', 'Modèle enregistré.');
        $this->redirect('/parametres/modeles');
    }

    public function destroy(string $id): void
    {
        $modeles = new ModeleCourrier();
        $modele  = $modeles->find((int) $id);
        if (!$modele) {
            $this->notFound('Modèle introuvable.');
        }
        if ((int) $modele['systeme'] === 1) {
            flash('error', 'Un modèle fourni ne peut pas être supprimé : désactivez-le.');
            $this->redirect('/parametres/modeles');
        }

        $modeles->delete((int) $id);
        Audit::log('modele_courrier_suppression', 'modele_courrier', (int) $id);
        flash('success', 'Modèle supprimé.');
        $this->redirect('/parametres/modeles');
    }

    private function formulaire(array $modele, string $mode, string $titre): void
    {
        $this->view('courriers/modele_form', [
            'titre'     => $titre,
            'modele'    => $modele,
            'mode'      => $mode,
            'variables' => CourrierService::VARIABLES,
            'types'     => ModeleCourrier::TYPES,
        ]);
    }

    private function collecter(): array
    {
        $code = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', (string) $this->post('code', '')) ?? '');
        return [
            'code'    => trim($code, '_'),
            'nom'     => (string) $this->post('nom', ''),
            'type'    => (string) $this->post('type', 'autre'),
            'sujet'   => strip_tags((string) $this->post('sujet', '')),
            // HTML de mise en forme uniquement (liste blanche) : aucun script ni evenement.
            'contenu' => HtmlSanitizer::nettoyer((string) ($_POST['contenu'] ?? '')),
            'actif'   => $this->post('actif') !== null ? 1 : 0,
        ];
    }

    private function valider(array $data, ?int $sauf): Validator
    {
        $validator = new Validator($data, ['nom' => 'Le nom', 'code' => 'Le code', 'contenu' => 'Le contenu']);
        $validator->required('nom')->length('nom', 2, 150)->required('code')->length('code', 2, 40)
            ->required('contenu')->in('type', array_keys(ModeleCourrier::TYPES));
        if ((new ModeleCourrier())->codeExiste((string) $data['code'], $sauf)) {
            $validator->custom('code', false, 'Ce code est déjà utilisé.');
        }
        return $validator;
    }
}
