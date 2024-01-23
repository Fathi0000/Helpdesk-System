<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Ticket;

class ReportService
{
    private $year, $month, $id;

    public function __construct(int $year, int $month, int $id = null)
    {
        $this->year = $year;
        $this->month = $month;
        $this->id = $id;
    }

    public function getMonthlyTickets()
    {
        return Ticket::whereYear('reporteddate', $this->year)
            ->whereMonth('reporteddate', $this->month)
            ->when(!is_null($this->id), function($query) {
                return $query->where('assignee', $this->id);
            })
            ->count(); 
    }
    
    public function getMonthlyDoneTickets()
    {
        return Ticket::whereYear('reporteddate', $this->year)
            ->whereMonth('reporteddate', $this->month)
            ->where('status', 'Closed')
            ->when(!is_null($this->id), function($query) {
                return $query->where('assignee', $this->id);
            })
            ->count(); 
    }
    
    public function getMonthlyPendingTickets()
    {
        return Ticket::whereYear('reporteddate', $this->year)
            ->whereMonth('reporteddate', $this->month)
            ->where('status', 'Pending')
            ->when(!is_null($this->id), function($query) {
                return $query->where('assignee', $this->id);
            })
            ->count(); 
    }
    
    public function getOverdueTickets(String $code = null)
    {
        $assignedTickets = Ticket::with('sla:id,resolution,warning')
            ->whereYear('reporteddate', $this->year)
            ->whereMonth('reporteddate', $this->month)
            ->where('status', 'Assigned')
            ->when(!is_null($this->id), function ($assignedTickets) {
                $assignedTickets->whereYear('reporteddate', $this->year)
                    ->whereMonth('reporteddate', $this->month)
                    ->where('status', 'Assigned')
                    ->where('assignee', $this->id);
            })->get();
    
        $assigned_plus = 0;
    
        foreach ($assignedTickets as $ticket) {
            try {
                if (!isset($ticket->reporteddate) || !isset($ticket->sla) || !is_object($ticket->sla)) {
                    // Log details about the ticket causing the issue
                    \Log::error('Invalid ticket details - Ticket ID: ' . optional($ticket)->id);
                    continue;
                }
    
                $time = Carbon::parse($ticket->reporteddate);
    
                // Check if 'sla' property exists and is an object
                if (isset($ticket->sla->resolution) && isset($ticket->sla->warning)) {
                    if ($code == 'red') {
                        $new_time = $time->copy()->addHours($ticket->sla->resolution);
                        if ($new_time->lt(now())) {
                            // Increment the counter properly
                            $assigned_plus++;
                        }
                    } elseif ($code == 'yellow') {
                        $warning_time = $time->copy()->addHours($ticket->sla->warning);
                        $resolution_time = $time->copy()->addHours($ticket->sla->resolution);
                        if (now()->between($warning_time, $resolution_time)) {
                            // Increment the counter properly
                            $assigned_plus++;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log the ticket details and error message
                \Log::error('Error processing ticket - Ticket ID: ' . optional($ticket)->id . ' - ' . $e->getMessage());
            }
        }
    
        return $assigned_plus;
    }

    
    
    public function getAllTechnicianTickets()
    {
        $technicians = User::role('Teknisi')->get();
        $allReport = collect([]);
    
        foreach ($technicians as $technician) {
            try {
                $report = new ReportService(now()->format('Y'), now()->format('m'), $technician->id);
    
                $combined = [
                    'name'    => $technician->name,
                    'assigned' => $report->getMonthlyTickets(),
                    'expired'  => $report->getOverdueTickets('red'),
                    'warning'  => $report->getOverdueTickets('yellow'),
                    'pending'  => $report->getMonthlyPendingTickets(),
                    'done'     => $report->getMonthlyDoneTickets(),
                ];
                $allReport->push($combined);
                // Debugging statement
                dd($combined);
            } catch (\Exception $e) {
                // Log the error message
                \Log::error('Error processing technician - Technician ID: ' . optional($technician)->id . ' - ' . $e->getMessage());
            }
        }
    
        return $allReport;
    }
    
}