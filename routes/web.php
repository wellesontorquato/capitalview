<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmprestimoController;
use App\Http\Controllers\ParcelaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EmprestimoReciboController; // ← importe o controller do recibo
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Se logado -> dashboard; senão -> login
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Perfil (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clientes
    Route::resource('clientes', ClienteController::class)
        ->parameters(['clientes' => 'cliente'])
        ->whereNumber('cliente');

    // Empréstimos (CRUD)
    Route::resource('emprestimos', EmprestimoController::class)
        ->parameters(['emprestimos' => 'emprestimo'])
        ->whereNumber('emprestimo');

    // Rotas extras de Empréstimos (cronograma, quitação, recibo, etc.)
    Route::prefix('emprestimos/{emprestimo}')
        ->whereNumber('emprestimo')
        ->group(function () {
            // Alias de show como "cronograma"
            Route::get('cronograma', [EmprestimoController::class, 'show'])
                ->name('emprestimos.cronograma');

            // (Re)gerar cronograma manual
            Route::post('gerar-cronograma', [EmprestimoController::class, 'gerarCronogramaManual'])
                ->name('emprestimos.gerar');

            // Prévia de quitação (SweetAlert)
            Route::get('quitacao-preview', [EmprestimoController::class, 'quitacaoPreview'])
                ->name('emprestimos.quitacaoPreview');

            // Quitar empréstimo
            Route::post('quitar', [EmprestimoController::class, 'quitar'])
                ->name('emprestimos.quitar');

            // ✅ Recibo do empréstimo (NÃO repete o prefixo)
            Route::get('recibo', [EmprestimoReciboController::class, 'download'])
                ->name('emprestimos.recibo');
        });

    // Pagamento de parcela (total/parcial)
    Route::post('/parcelas/{parcela}/pagar', [ParcelaController::class, 'pagar'])
        ->whereNumber('parcela')
        ->name('parcelas.pagar');

    Route::put('/parcelas/{parcela}', [ParcelaController::class, 'update'])
        ->whereNumber('parcela')
        ->name('parcelas.update');
});

// Rotas de auth (Breeze/Fortify/etc.)
require __DIR__.'/auth.php';
