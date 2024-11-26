<?php

namespace App\Http\Controllers;

use App\Models\Tuition;
use App\Http\Requests\StoreTuitionRequest;
use App\Http\Requests\UpdateTuitionRequest;

class TuitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTuitionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Tuition $tuition)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTuitionRequest $request, Tuition $tuition)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tuition $tuition)
    {
        //
    }
}
