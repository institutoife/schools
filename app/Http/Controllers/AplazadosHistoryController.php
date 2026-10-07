<?php

namespace App\Http\Controllers;

use App\Services\EducationHistory;

class AplazadosHistoryController extends Controller
{
    public function __invoke(EducationHistory $history)
    {
        return view('reports.aplazados-history', ['departmentHistory' => $history->departments()]);
    }
}
