<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FontController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegalController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::post('/templates/import', [TemplateController::class, 'importPackage'])->middleware('throttle:8,1')->name('templates.import');
Route::get('/templates/starters/{starter}/preview', [TemplateController::class, 'previewStarter'])->name('templates.starters.preview');
Route::post('/templates/starters/{starter}', [TemplateController::class, 'createStarter'])->middleware('throttle:15,1')->name('templates.starters.create');
Route::get('/templates/{template}/export', [TemplateController::class, 'exportPackage'])->name('templates.export');
Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
Route::put('/templates/{template}/rename', [TemplateController::class, 'rename'])->name('templates.rename');
Route::post('/templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
Route::post('/templates/{template}/archive', [TemplateController::class, 'archive'])->name('templates.archive');
Route::post('/templates/{template}/restore', [TemplateController::class, 'restore'])->name('templates.restore');
Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
Route::put('/templates/{template}/elements', [TemplateController::class, 'save'])->name('templates.save');
// Local, private editor draft: never replaces a saved template design.
Route::get('/templates/{template}/draft', [\App\Http\Controllers\EditorDraftController::class, 'show'])->name('templates.draft.show');
Route::post('/templates/{template}/draft', [\App\Http\Controllers\EditorDraftController::class, 'store'])->middleware('throttle:120,1')->name('templates.draft.store');
Route::delete('/templates/{template}/draft', [\App\Http\Controllers\EditorDraftController::class, 'destroy'])->name('templates.draft.destroy');
Route::get('/templates/{template}/file', [TemplateController::class, 'file'])->name('templates.file');
Route::post('/templates/{template}/signatures', [SignatureController::class, 'store'])->middleware('throttle:20,1')->name('signatures.store');
Route::get('/signatures/{id}', [SignatureController::class, 'show'])->where('id', '[a-f0-9]{32}')->name('signatures.show');
Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
Route::get('/batches/new', [BatchController::class, 'create'])->name('batches.create');
Route::post('/batches', [BatchController::class, 'store'])->name('batches.store');
Route::post('/batches/proof', [BatchController::class, 'proof'])->middleware('throttle:30,1')->name('batches.proof');
Route::post('/batches/import-recipients', [BatchController::class, 'importRecipients'])->middleware('throttle:30,1')->name('batches.import-recipients');
Route::post('/batches/sample-preview', [BatchController::class, 'previewSample'])->middleware('throttle:20,1')->name('batches.preview-sample');
Route::delete('/batches/{batch}/trash', [\App\Http\Controllers\TrashController::class, 'trashBatch'])->name('batches.trash');
Route::post('/batches/trash/{batchId}/restore', [\App\Http\Controllers\TrashController::class, 'restoreBatch'])->whereNumber('batchId')->name('batches.restore');
Route::delete('/batches/trash/{batchId}/purge', [\App\Http\Controllers\TrashController::class, 'purgeBatch'])->whereNumber('batchId')->name('batches.purge');
Route::delete('/certificates/{certificate}/trash', [\App\Http\Controllers\TrashController::class, 'trashCertificate'])->name('certificates.trash');
Route::post('/certificates/trash/{certificateId}/restore', [\App\Http\Controllers\TrashController::class, 'restoreCertificate'])->whereNumber('certificateId')->name('certificates.restore');
Route::delete('/certificates/trash/{certificateId}/purge', [\App\Http\Controllers\TrashController::class, 'purgeCertificate'])->whereNumber('certificateId')->name('certificates.purge');
Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
Route::get('/batches/{batch}/artwork', [BatchController::class, 'artwork'])->name('batches.artwork');
Route::post('/batches/{batch}/process', [BatchController::class, 'process'])->name('batches.process');
Route::post('/batches/{batch}/retry', [BatchController::class, 'retry'])->name('batches.retry');
Route::put('/batches/{batch}/rename', [BatchController::class, 'rename'])->name('batches.rename');
Route::post('/batches/{batch}/download-selected', [BatchController::class, 'downloadSelected'])->name('batches.download-selected');
Route::get('/batches/{batch}/download', [BatchController::class, 'download'])->name('batches.download');
Route::get('/batches/{batch}/print-sheets', [BatchController::class, 'printSheets'])->name('batches.print-sheets');
Route::get('/batches/{batch}/combined-pdf', [BatchController::class, 'downloadCombinedPdf'])->name('batches.combined-pdf');
Route::get('/certificates/{certificate}/preview', [BatchController::class, 'preview'])->name('certificates.preview');
Route::get('/certificates/{certificate}/download', [BatchController::class, 'certificate'])->name('certificates.download');

Route::get('/fonts', [FontController::class, 'index'])->name('fonts.index');
Route::post('/fonts', [FontController::class, 'store'])->name('fonts.store');
// Allow installing the full 10-font catalog in one session while retaining rate limiting.
Route::post('/fonts/catalog/{catalogId}', [FontController::class, 'installCurated'])->middleware('throttle:30,1')->name('fonts.catalog.install');
Route::post('/fonts/{fontId}/variants', [FontController::class, 'storeVariant'])->middleware('throttle:30,1')->name('fonts.variants.store');
Route::patch('/fonts/{fontId}', [FontController::class, 'update'])->name('fonts.update');
Route::delete('/fonts/{fontId}', [FontController::class, 'destroy'])->name('fonts.destroy');
Route::get('/fonts/{fontId}/file', [FontController::class, 'file'])->name('fonts.file');

// Built-in User Guide: read-only page, no database access.
Route::view('/guide', 'guide.index')->name('guide.index');

// One Legal & Privacy page. Legacy document URLs redirect to their section on it.
Route::get('/legal', [LegalController::class, 'index'])->name('legal.index');
Route::get('/legal/{slug}', [LegalController::class, 'document'])->where('slug', 'privacy|terms|cookies|accessibility|licenses')->name('legal.document');
