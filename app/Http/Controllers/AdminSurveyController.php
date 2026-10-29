<?php

namespace App\Http\Controllers;

use App\Models\ServiceSurveyResponse;
use Illuminate\Http\Request;

class AdminSurveyController extends Controller {
    public function index(Request $request) {
        abort_unless($request->user()?->isAdmin(), 403);
        $summary = ServiceSurveyResponse::query()->selectRaw(
            'COUNT(*) as total, AVG(overall_rating) as overall, AVG(staff_rating) as staff, AVG(clarity_rating) as clarity, AVG(speed_rating) as speed'
        )->first();
        $perService = ServiceSurveyResponse::query()->join('tickets', 'tickets.id', '=', 'service_survey_responses.ticket_id')
            ->select('tickets.layanan')->selectRaw('COUNT(*) as total, AVG(overall_rating) as average')
            ->groupBy('tickets.layanan')->orderBy('tickets.layanan')->get();
        $responses = ServiceSurveyResponse::query()->with('ticket:id,ticket_number,layanan')
            ->latest()->paginate(15);

        return response()->view('admin.surveys', compact('summary', 'perService', 'responses'))
            ->header('Cache-Control', 'no-store, private');
    }
}
