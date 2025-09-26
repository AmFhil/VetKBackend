<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Create a new appointment.
     */
    public function makeAppointment(Request $request)
    {
        $request->validate([
            'date'      => 'required|date',
            'time'      => 'required',
            'doctor_id' => 'required|exists:users_registration,id',
        ]);

        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $doctor = User::findOrFail($request->doctor_id);

        $appointment = Appointment::create([
            'user_id'       => $user->id,
            'doctor_id'     => $doctor->id,
            'appointment_at'=> Carbon::createFromFormat('d/m/Y H:i', $request->date . " " . $request->time),
            'status'        => 'scheduled',
        ]);

        // Log activity for patient
        $user->logActivity(
            'appointment_created',
            'Appointment Scheduled',
            "You booked an appointment with Dr. {$doctor->first_name} {$doctor->last_name} on {$appointment->appointment_at->format('d M Y, H:i')}.",
            ['appointment_id' => $appointment->id, 'doctor_id' => $doctor->id]
        );

        // Notify doctor
        $doctor->sendNotification(
            'appointment_created',
            'New Appointment',
            "{$user->first_name} {$user->last_name} booked an appointment with you on {$appointment->appointment_at->format('d M Y, H:i')}.",
            ['appointment_id' => $appointment->id, 'user_id' => $user->id]
        );

        return response()->json([
            'message' => 'Appointment successfully booked.',
            'appointment' => $appointment,
        ], 200);
    }

    /**
     * Get appointment details (accessible to both patient and doctor).
     */
    public function getAppointment($id)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $appointment = Appointment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('doctor_id', $user->id);
            })
            ->firstOrFail();

        return response()->json([
            'message' => 'Appointment retrieved successfully.',
            'appointment' => $appointment,
        ], 200);
    }

    /**
     * Cancel an appointment.
     */
    public function cancelAppointment($id)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $appointment = Appointment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('doctor_id', $user->id);
            })
            ->firstOrFail();

        $appointment->update(['status' => 'cancelled']);

        $doctor = User::find($appointment->doctor_id);
        $patient = User::find($appointment->user_id);

        // Log activity for canceller
        $user->logActivity(
            'appointment_cancelled',
            'Appointment Cancelled',
            "You cancelled the appointment scheduled on {$appointment->appointment_at->format('d M Y, H:i')}.",
            ['appointment_id' => $appointment->id]
        );

        // Notify the other party
        $recipient = $user->id === $patient->id ? $doctor : $patient;
        $recipient->sendNotification(
            'appointment_cancelled',
            'Appointment Cancelled',
            "{$user->first_name} {$user->last_name} cancelled the appointment scheduled on {$appointment->appointment_at->format('d M Y, H:i')}.",
            ['appointment_id' => $appointment->id, 'cancelled_by' => $user->id]
        );

        return response()->json([
            'message' => 'Appointment cancelled successfully.',
            'appointment' => $appointment,
        ], 200);
    }

    /**
     * Complete an appointment.
     */
    public function completeAppointment($id)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $appointment = Appointment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('doctor_id', $user->id);
            })
            ->firstOrFail();

        $appointment->update(['status' => 'completed']);

        $doctor = User::find($appointment->doctor_id);
        $patient = User::find($appointment->user_id);

        // Log activity for completer
        $user->logActivity(
            'appointment_completed',
            'Appointment Completed',
            "You marked the appointment with " . ($user->id === $patient->id ? "Dr. {$doctor->first_name}" : "{$patient->first_name}") .
            " on {$appointment->appointment_at->format('d M Y, H:i')} as completed.",
            ['appointment_id' => $appointment->id]
        );

        // Notify the other party
        $recipient = $user->id === $patient->id ? $doctor : $patient;
        $recipient->sendNotification(
            'appointment_completed',
            'Appointment Completed',
            "{$user->first_name} {$user->last_name} marked the appointment on {$appointment->appointment_at->format('d M Y, H:i')} as completed.",
            ['appointment_id' => $appointment->id, 'completed_by' => $user->id]
        );

        return response()->json([
            'message' => 'Appointment marked as completed.',
            'appointment' => $appointment,
        ], 200);
    }
}
