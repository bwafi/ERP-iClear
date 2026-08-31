# KPI Calculation Service Architecture - CodeIgniter 4 ERP

Complete KPI and incentive calculation system with manual evaluation, automatic KPI calculation, incentive rules, and salary structure support.

## Architecture Overview

### Service Files

1. **KpiCalculatorInterface.php** - Base interface for all KPI calculators
2. **OmsetTokoCalculator.php** - Calculates store/employee sales revenue
3. **CustomerCalculator.php** - Counts unique customers served by employee
4. **OmsetCabangCalculator.php** - Calculates branch/unit level revenue
5. **KpiCalculationService.php** - Main orchestrator for automatic KPI calculations
6. **KpiEvaluationService.php** - Handles manual KPI scoring (1-5 scale)
7. **IncentiveCalculationService.php** - Calculates incentives based on rules
8. **SalaryCalculationService.php** - Calculates employee salaries with components

## Key Features

### Manual KPI Evaluation

- Score range: 1-5
- Automatic normalization to 0-100 scale
- Formula: `normalized_score = (raw_score / max_score) * 100`
- Weighted score calculation: `weighted_score = (normalized_score / 100) * weight`
- Automatic weight lookup from position configuration
- Upsert support (insert or update)

### Automatic KPI Calculation

- Strategy pattern with pluggable calculators
- Strategy identifier stored in `calculation_method` field (NOT raw SQL)
- Achievement calculation: `achievement = (actual / target) * 100` (capped at 100%)
- Support for multiple calculation strategies:
  - `omset_toko`: Employee sales revenue
  - `customer_count`: Unique customers
  - `omset_cabang`: Unit/branch level revenue

### Weight Validation

- Validates total weights per position = 100%
- Tolerance: 0.01% margin
- Retrieves effective weights by date range
- Returns detailed validation results

### Incentive Calculation

- Calculation types: percentage, tier, flat
- Minimum achievement threshold enforcement
- Division methods: divide_by_3, divide_by_4, per_unit
- Tiered incentive structure:
  - < 70%: 0
  - 70-90%: 70% of base
  - 90-110%: 100% of base
  - 110-130%: 120% of base
  - ≥ 130%: 150% of base

### Salary Calculation

- Component types: fixed, percent_of_base, percent_of_kpi
- Batch salary calculation support
- KPI achievement percentage support
- Flexible salary structure per position

## Database Schema Integration

### Tables Used

- `kpi_components` - KPI definitions with strategy identifiers
- `kpi_weights` - Position-based KPI weights (must sum to 100)
- `kpi_targets` - KPI targets by unit/position
- `kpi_evaluations` - Manual KPI evaluation records
- `incentive_rules` - Incentive calculation rules
- `salary_components` - Salary component definitions
- `salary_structures` - Position-based salary structures
- `akun` - Employee records (linked by ID_AKUN)
- `jabatan` - Position/role definitions
- `unit` - Organization units/branches
- `penjualan` - Sales transactions
- `detail_penjualan` - Sales line items

## Usage Examples

### Manual KPI Evaluation

```php
$evaluationService = new KpiEvaluationService();

$evaluationData = [
    'employee_id' => 1,
    'kpi_component_id' => 5,
    'evaluator_id' => 10,
    'raw_score' => 4,              // Score 1-5
    'max_score' => 5,               // Defaults to 5
    'notes' => 'Good performance',
    'period_year' => 2024,
    'period_month' => 8,
];

$result = $evaluationService->recordEvaluation($evaluationData);

if ($result['success']) {
    // Result includes:
    // - normalized_score: 80 (4/5 * 100)
    // - weighted_score: calculated based on position weight
}
```

### Calculate Automatic KPI

```php
$kpiService = new KpiCalculationService();

$results = $kpiService->calculateAutomaticKpi(
    $employeeId = 1,
    $unitId = 1,
    $month = 8,
    $year = 2024
);

// Returns array with:
// - kpi_component_id
// - code, name
// - actual_value, target_value
// - achievement_percent (capped at 100)
// - weight, weighted_score
```

### Validate Position Weights

```php
$kpiService = new KpiCalculationService();

$validation = $kpiService->getWeightValidationResult($positionId = 35);

// Returns:
// - total_weight: 100.00
// - is_valid: true
// - difference: 0.00
// - weights: array of component weights
```

### Calculate Incentive

```php
$incentiveService = new IncentiveCalculationService();

$result = $incentiveService->calculateIncentive(
    $positionId = 41,
    $kpiComponentId = 1,
    $achievement = 125,              // Percentage
    $baseAmount = 50000000,          // Base salary
    $date = null                     // Defaults to today
);

// Returns:
// - success: true/false
// - incentive_amount: calculated result
// - reason: if not successful
```

### Calculate Employee Salary

```php
$salaryService = new SalaryCalculationService();

$result = $salaryService->calculateSalary(
    $employeeId = 1,
    $positionId = 35,
    $baseSalary = 1500000,
    $kpiAchievement = 95,            // Optional
    $incentiveAmount = 100000        // Optional
);

// Returns:
// - success: true/false
// - components: array of salary components with amounts
// - total_salary: final calculated salary
```

### Batch Salary Calculation

```php
$salaryService = new SalaryCalculationService();

$results = $salaryService->calculateBatchSalaries(
    $positionId = 35,
    $employees = [1, 2, 3, 4, 5],
    $baseSalary = 1500000,
    $kpiAchievements = [1 => 95, 2 => 100, 3 => 85],
    $incentiveAmounts = [1 => 100000, 2 => 150000]
);

// Returns summary with total_salary_paid and per-employee breakdowns
```

### Get Employee Aggregate Score

```php
$evaluationService = new KpiEvaluationService();

$aggregate = $evaluationService->getEmployeeAggregateScore(
    $employeeId = 1,
    $year = 2024,
    $month = 8
);

// Returns:
// - total_weighted_score
// - average_normalized_score
// - evaluation_count
// - evaluations: detailed array
```

## Adding New KPI Calculators

1. Create new class implementing `KpiCalculatorInterface`:

```php
class NewCalculator implements KpiCalculatorInterface
{
    public function calculate($employeeId, $unitId, $month, $year)
    {
        // Calculation logic
        return $value;
    }
}
```

1. Register in `KpiCalculationService::registerCalculators()`:

```php
$this->calculators['new_strategy'] = new NewCalculator();
```

1. Set `calculation_method` to `'new_strategy'` in `kpi_components` table.

## Data Flow

### Manual KPI Recording

```
Input Data → Validation → Score Normalization → Weight Lookup
    → Weighted Score Calculation → Database Storage → Response
```

### Automatic KPI Calculation

```
Employee/Unit/Period → Strategy Pattern → Calculator Execution
    → Target Lookup → Achievement Calculation → Weight Application
    → Result Array → Response
```

### Incentive Calculation

```
Achievement % + Rules → Minimum Check → Type-based Calculation
    → Division Method → Final Amount → Response
```

### Salary Calculation

```
Position → Salary Components → Component Type Logic
    → Amount Calculation → Aggregation → Total Salary
```

## Validation Rules

### Manual KPI

- employee_id: required
- kpi_component_id: required (must exist)
- evaluator_id: required
- raw_score: required, 1-5 range
- period_year, period_month: required

### Weight Configuration

- Total weight per position must = 100% (±0.01% tolerance)
- Effective date ranges must not overlap for same component/position

### Incentive Rules

- achievement must be ≥ minimum_achievement to trigger payment
- base_value and calculation_type determine calculation method

## Error Handling

All services return structured responses:

```php
[
    'success' => true/false,
    'error' => 'Error message if failed',
    'data' => [...],    // or specific result fields
    'warnings' => [...]  // Optional validation warnings
]
```

## Performance Considerations

- Caches effective dates to reduce database queries
- Uses indexed lookups on (component_id, position_id, effective_date)
- Supports batch operations for large-scale calculations
- All date queries optimized with proper indexes

## Security Notes

- Does NOT store raw SQL in database
- Strategy identifiers are pre-defined strings only
- All calculations use parameterized queries
- Input validation on all user-facing methods
