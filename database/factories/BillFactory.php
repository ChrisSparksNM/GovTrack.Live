<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chamber = $this->faker->randomElement(['house', 'senate']);
        $billTypes = [
            'house' => ['HR', 'HJRES', 'HRES', 'HCONRES'],
            'senate' => ['S', 'SJRES', 'SRES', 'SCONRES']
        ];
        $billType = $this->faker->randomElement($billTypes[$chamber]);
        $billNumber = $this->faker->numberBetween(1, 9999);
        
        return [
            'congress_id' => "118-{$billType}{$billNumber}",
            'title' => $this->faker->sentence(12),
            'number' => "{$billType} {$billNumber}",
            'chamber' => $chamber,
            'introduced_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'status' => $this->faker->randomElement([
                'Introduced in House',
                'Introduced in Senate',
                'Passed House',
                'Passed Senate',
                'Referred to Committee',
                'Reported by Committee',
                'Became Public Law',
                'Failed of Passage'
            ]),
            'sponsor_name' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'sponsor_party' => $this->faker->randomElement(['D', 'R', 'I']),
            'sponsor_state' => $this->faker->stateAbbr(),
            'full_text' => $this->faker->paragraphs(10, true),
            'summary_url' => $this->faker->url(),
            'cosponsors' => $this->generateCosponsors(),
        ];
    }

    /**
     * Generate sample cosponsors data
     */
    private function generateCosponsors(): array
    {
        $count = $this->faker->numberBetween(0, 15);
        $cosponsors = [];
        
        for ($i = 0; $i < $count; $i++) {
            $cosponsors[] = [
                'name' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
                'party' => $this->faker->randomElement(['D', 'R', 'I']),
                'state' => $this->faker->stateAbbr(),
            ];
        }
        
        return $cosponsors;
    }
}
