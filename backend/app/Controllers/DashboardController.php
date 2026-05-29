<?php
// app/Controllers/DashboardController.php
namespace App\Controllers;
use Core\Controller;
use App\Models\Lote;
use App\Models\Combo;
use App\Models\Alerta;

class DashboardController extends Controller {
    public function index(): void {
        $loteModel  = new Lote();
        $comboModel = new Combo();
        $alertaModel = new Alerta();

        $this->json([
            'kpis'         => $loteModel->kpis(),
            'distribuicao' => $loteModel->distribuicao(),
            'lotes_risco'  => $loteModel->emRisco(),
            'combos'       => $comboModel->allWithDetails(),
            'alertas'      => array_slice($alertaModel->allWithDetails(), 0, 5),
        ]);
    }
}
