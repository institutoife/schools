<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EducationHistory
{
    public const DEPARTMENTS = ['BENI', 'CHUQUISACA', 'COCHABAMBA', 'LA PAZ', 'ORURO', 'PANDO', 'POTOSI', 'SANTA CRUZ', 'TARIJA'];

    public function school($statistics): array
    {
        $latest = $statistics->sortByDesc('id')->unique(fn ($s) => $s->anio.'|'.$s->categoria);
        $years = array_unique(array_merge(range(2021, 2025), $latest->pluck('anio')->filter(fn ($y) => $y && $y <= 2025)->all()));
        sort($years);
        $result = [];
        foreach ($years as $year) {
            $row = ['year' => (int) $year];
            foreach (['matricula', 'promovidos', 'reprobados', 'abandono'] as $category) {
                $stat = $latest->first(fn ($s) => (int) $s->anio === (int) $year && $s->categoria === $category);
                $row[$category] = $stat && is_numeric($stat->total) ? (int) $stat->total : null;
            }
            $rep = $latest->first(fn ($s) => (int) $s->anio === (int) $year && $s->categoria === 'reprobados');
            $row['hombres'] = $rep && is_numeric($rep->hombre) ? (int) $rep->hombre : null;
            $row['mujeres'] = $rep && is_numeric($rep->mujer) ? (int) $rep->mujer : null;
            $row['tasa'] = $this->percent($row['reprobados'], $row['matricula']);
            $result[] = $row;
        }
        return $result;
    }

    public function departments(): array
    {
        $lastYear = (int) (DB::table('estadisticas')->where('categoria', 'reprobados')->whereBetween('anio', [2021, now()->year])->whereNotNull('total')->max('anio') ?? 2025);
        $latest = DB::table('estadisticas')->selectRaw('MAX(id) as id')->whereBetween('anio', [2021, $lastYear])->groupBy('school_id', 'anio', 'categoria');
        $pivot = DB::table('estadisticas as e')->joinSub($latest, 'latest', 'latest.id', '=', 'e.id')
            ->select('e.school_id', 'e.anio');
        foreach (['reprobados' => 'rep', 'matricula' => 'mat'] as $category => $prefix) {
            foreach (['total' => '', 'hombre' => '_h', 'mujer' => '_m'] as $field => $suffix) {
                $pivot->selectRaw("MAX(CASE WHEN e.categoria = '$category' AND e.$field IS NOT NULL AND TRIM(CAST(e.$field AS CHAR)) != '' THEN CAST(e.$field AS DECIMAL(18,0)) END) as $prefix$suffix");
            }
        }
        $pivot->groupBy('e.school_id', 'e.anio');
        $locations = DB::table('ubicacions')->selectRaw('MAX(id) as id')->groupBy('school_id');
        $query = DB::query()->fromSub($pivot, 's')->join('ubicacions as u', 'u.school_id', '=', 's.school_id')
            ->joinSub($locations, 'ul', 'ul.id', '=', 'u.id')->select('u.departamento', 's.anio');
        foreach (['rep', 'rep_h', 'rep_m', 'mat'] as $field) {
            $query->selectRaw("SUM(s.$field) as $field, COUNT(s.$field) as n_$field");
        }
        $query->selectRaw('SUM(CASE WHEN rep IS NOT NULL AND mat > 0 THEN rep END) as paired_rep, SUM(CASE WHEN rep IS NOT NULL AND mat > 0 THEN mat END) as paired_mat, SUM(CASE WHEN rep IS NOT NULL AND mat > 0 THEN 1 ELSE 0 END) as paired_schools');
        $rows = $query->groupBy('u.departamento', 's.anio')->get();
        $groups = array_fill_keys(self::DEPARTMENTS, []);
        foreach ($rows as $row) {
            $department = Str::upper(Str::ascii(trim($row->departamento ?? '')));
            if (!isset($groups[$department])) continue;
            $groups[$department][(int) $row->anio][] = $row;
        }
        $result = [];
        foreach ($groups as $department => $years) {
            $history = [];
            foreach (range(2021, $lastYear) as $year) {
                $source = collect($years[$year] ?? []);
                $value = fn ($field) => $source->sum('n_'.$field) ? (int) $source->sum($field) : null;
                $rep = $value('rep');
                $h = $value('rep_h'); $m = $value('rep_m');
                // Zero is a reported value. Missing sex values stay unknown, never become zero.
                $sexComplete = $rep !== null && $source->sum('n_rep') === $source->sum('n_rep_h') && $source->sum('n_rep') === $source->sum('n_rep_m') && $h !== null && $m !== null && $h + $m === $rep;
                $history[] = ['year' => $year, 'total' => $rep, 'hombres' => $h, 'mujeres' => $m,
                    'porcentaje_hombres' => $this->percent($h, $rep), 'porcentaje_mujeres' => $this->percent($m, $rep),
                    'matricula' => $value('mat'), 'tasa' => $this->percent($source->sum('paired_rep'), $source->sum('paired_mat')),
                    'colegios' => (int) $source->sum('n_rep'), 'colegios_tasa' => (int) $source->sum('paired_schools'),
                    'base_tasa' => (int) $source->sum('paired_mat'), 'casos_tasa' => (int) $source->sum('paired_rep'), 'sexo_completo' => $sexComplete, 'projection' => false];
            }
            $history[] = $this->project($history);
            $result[$department] = $history;
        }
        return $result;
    }

    public function percent($numerator, $denominator): ?float
    {
        if ($numerator === null || !$denominator || $numerator > $denominator) return null;
        return round($numerator * 100 / $denominator, 2);
    }

    public function forecast(array $history, string $field, int $targetYear = 2026): ?int
    {
        $points = array_values(array_filter($history, fn ($p) => isset($p[$field]) && $p['year'] < $targetYear));
        if (count($points) < 3 || max(array_column($points, 'year')) !== $targetYear - 1) return null;
        $x = array_sum(array_column($points, 'year')) / count($points);
        $y = array_sum(array_column($points, $field)) / count($points);
        $num = $den = 0;
        foreach ($points as $p) { $num += ($p['year'] - $x) * ($p[$field] - $y); $den += ($p['year'] - $x) ** 2; }
        return $den ? max(0, (int) round($y + $num / $den * ($targetYear - $x))) : null;
    }

    public function project(array $history): array
    {
        $targetYear = max(array_column($history, 'year')) + 1;
        $total = $this->forecast($history, 'total', $targetYear);
        $sexRows = array_map(fn ($r) => $r['sexo_completo'] ? $r : array_merge($r, ['hombres' => null, 'mujeres' => null]), $history);
        $h = $this->forecast($sexRows, 'hombres', $targetYear); $m = $this->forecast($sexRows, 'mujeres', $targetYear);
        if ($h !== null && $m !== null && $total !== null && ($h + $m > 0 || $total === 0)) {
            $h = $h + $m > 0 ? (int) round($total * $h / ($h + $m)) : 0;
            $m = $total - $h;
        } else { $h = $m = null; }
        $pairedRep = $this->forecast(array_map(fn ($r) => array_merge($r, ['casos_tasa' => $r['base_tasa'] > 0 ? $r['casos_tasa'] : null]), $history), 'casos_tasa', $targetYear);
        $pairedMat = $this->forecast(array_map(fn ($r) => array_merge($r, ['base_tasa' => $r['base_tasa'] > 0 ? $r['base_tasa'] : null]), $history), 'base_tasa', $targetYear);
        return ['year' => $targetYear, 'total' => $total, 'hombres' => $h, 'mujeres' => $m, 'porcentaje_hombres' => $this->percent($h, $total), 'porcentaje_mujeres' => $this->percent($m, $total), 'matricula' => $this->forecast($history, 'matricula', $targetYear),
            'tasa' => $this->percent($pairedRep, $pairedMat), 'projection' => true, 'colegios' => null, 'colegios_tasa' => null, 'base_tasa' => $pairedMat, 'casos_tasa' => $pairedRep, 'sexo_completo' => $h !== null];
    }
}
