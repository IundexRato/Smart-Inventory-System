<?php
// app/Controllers/FornecedorController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Fornecedor;

class FornecedorController extends Controller {
    private Fornecedor $model;

    public function __construct() {
        $this->model = new Fornecedor();
    }

    public function index(): void {
        $this->json($this->model->all('razao_social'));
    }
}
