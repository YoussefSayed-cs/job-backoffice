<?php

namespace App\Http\Controllers;


use App\Models\job_application;
use App\Events\JobApplicationSubmitted;
use App\Notifications\newJobApply;
use App\Http\Requests\JobApplication\JobApplicationUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = job_application::with(['Owner', 'resume', 'jobVacancy.company'])->latest();

        if (Auth::user()->role == 'company-owner') {
            $query->whereHas('jobVacancy', function ($q) {
                $q->where('companyID', Auth::user()->company->id);
            });
        }

        // Filter by job vacancy if provided
        if ($request->has('job')) {
            $query->where('jobVacancyID', $request->input('job'));
        }

        //clear archive
        if ($request->input('archived') == 'true') {
            $query->onlyTrashed();
        }

        // Pagination
        $job_applications = $query->paginate(10)->onEachSide(1);

        return view('JobApplication.index', compact('job_applications'));
    }


    public function show(string $id)
    {
        $job_application = job_application::withTrashed()
            ->with(['Owner', 'resume', 'jobVacancy.company'])
            ->findOrFail($id);

        // company-owner can only view applications for their own company
        if (Auth::user()->role === 'company-owner') {
            abort_unless(
                optional($job_application->jobVacancy?->company)->id === Auth::user()->company?->id,
                403
            );
        }

        return view('JobApplication.show', compact('job_application'));
    }

    /**
     * Stream / redirect to the resume file stored in cloud storage.
     */
    public function viewResume(string $id)
    {
        $job_application = job_application::with(['resume', 'jobVacancy.company'])
            ->withTrashed()
            ->findOrFail($id);

        // company-owner can only view their own company's applications
        if (Auth::user()->role === 'company-owner') {
            abort_unless(
                optional($job_application->jobVacancy?->company)->id === Auth::user()->company?->id,
                403
            );
        }

        $fileUri = $job_application->resume?->fileUri;

        if (!$fileUri) {
            abort(404, 'No resume file found for this application.');
        }

        try {
            return Storage::disk('cloud')->download($fileUri);
        } catch (\Throwable $e) {
            \Log::error('Resume Download Error: ' . $e->getMessage());
            abort(500, 'Could not retrieve resume file: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $job_application = job_application::findOrFail($id);
        return view('JobApplication.edit', compact('job_application'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobApplicationUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        $job_application = job_application::findOrFail($id);
        $job_application->update($validated);

        return redirect()->route('job-applications.index')->with('success', 'Job application updated successfully.');
    }



public function store(Request $request)
{
    $jobApplication = job_application::create([
        'jobVacancyID' => $request->jobVacancyID,
        'userID'       => Auth::id(),
        'status'       => 'pending',
    ]);

    JobApplicationSubmitted::dispatch(Auth::user(), $jobApplication);

    return back()->with('success', 'Application sent successfully');
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $job_application = job_application::findOrFail($id);
        $job_application->delete();

        return redirect()->route('job-applications.index')->with('success', 'Job application archived successfully.');
    }

    public function restore(string $id)
    {
        $job_application = job_application::withTrashed()->findOrFail($id);
        $job_application->restore();

        return redirect()->route('job-applications.index')->with('success', 'Job application restored successfully.');
    }
}
