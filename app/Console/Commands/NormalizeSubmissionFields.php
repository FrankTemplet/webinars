<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;

class NormalizeSubmissionFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submissions:normalize-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize submission data fields (e.g., number_employees → employee_range)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting normalization of submission fields...');

        $submissions = Submission::all();
        $updated = 0;
        $skipped = 0;

        foreach ($submissions as $submission) {
            $data = $submission->data;
            $modified = false;

            // Normalize number_employees to employee_range
            if (isset($data['number_employees']) && !isset($data['employee_range'])) {
                $data['employee_range'] = $data['number_employees'];
                unset($data['number_employees']);
                $modified = true;
            }

            // You can add more field normalizations here if needed
            // Example:
            // if (isset($data['nombre']) && !isset($data['first_name'])) {
            //     $data['first_name'] = $data['nombre'];
            //     unset($data['nombre']);
            //     $modified = true;
            // }

            if ($modified) {
                $submission->data = $data;
                $submission->save();
                $updated++;
                $this->line("✓ Updated submission ID: {$submission->id}");
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Normalization complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Total', $submissions->count()],
            ]
        );

        return Command::SUCCESS;
    }
}
