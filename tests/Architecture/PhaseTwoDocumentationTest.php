<?php

test('the phase two architecture package contains every required artifact', function () {
    $projectRoot = dirname(__DIR__, 2);
    $requiredDocuments = [
        'docs/02-architecture/architecture-overview.md',
        'docs/02-architecture/module-boundaries.md',
        'docs/02-architecture/database-design.md',
        'docs/02-architecture/erd.md',
        'docs/02-architecture/data-dictionary.md',
        'docs/02-architecture/multi-company-security-model.md',
        'docs/02-architecture/phase-2-exit-review.md',
        'docs/adr/0001-modular-monolith.md',
        'docs/adr/0002-legal-entity-isolation.md',
        'docs/adr/0003-temporal-data-and-utc.md',
        'docs/adr/0004-identifiers-and-database-conventions.md',
        'docs/adr/0005-decimal-money-and-rounding.md',
        'docs/adr/0006-generic-approval-snapshots.md',
        'docs/adr/0007-audit-and-sensitive-data.md',
    ];

    foreach ($requiredDocuments as $relativePath) {
        $path = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        expect(is_file($path))->toBeTrue()
            ->and(filesize($path))->toBeGreaterThan(200)
            ->and((string) file_get_contents($path))->not->toContain('[TODO]', 'TODO:', 'TBD:');
    }
});

test('the data dictionary covers every entity required by the master prompt', function () {
    $dictionary = (string) file_get_contents(
        dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'02-architecture'.DIRECTORY_SEPARATOR.'data-dictionary.md',
    );
    $requiredTables = [
        'users',
        'legal_entities',
        'branches',
        'divisions',
        'departments',
        'positions',
        'employees',
        'employee_contacts',
        'emergency_contacts',
        'employee_documents',
        'employee_bank_accounts',
        'employee_tax_profiles',
        'employee_bpjs_profiles',
        'employment_histories',
        'contracts',
        'salary_components',
        'employee_salary_components',
        'salary_histories',
        'work_schedules',
        'holidays',
        'attendance_events',
        'attendance_records',
        'attendance_corrections',
        'leave_types',
        'leave_entitlements',
        'leave_ledger_entries',
        'leave_requests',
        'overtime_requests',
        'overtime_calculations',
        'payroll_periods',
        'payroll_runs',
        'payroll_run_employees',
        'payroll_items',
        'payroll_adjustments',
        'tax_rule_sets',
        'bpjs_rule_sets',
        'payslips',
        'approval_definitions',
        'approval_steps',
        'approval_instances',
        'approval_actions',
        'approval_delegations',
        'notifications',
        'report_snapshots',
        'import_batches',
        'import_rows',
        'audit_logs',
    ];

    foreach ($requiredTables as $table) {
        expect($dictionary)->toContain('`'.$table.'`');
    }
});

test('accepted architecture decisions contain the approved invariants', function () {
    $projectRoot = dirname(__DIR__, 2);
    $adrDirectory = $projectRoot.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'adr';
    $acceptedAdrs = glob($adrDirectory.DIRECTORY_SEPARATOR.'*.md');

    expect($acceptedAdrs)->toHaveCount(7);

    foreach ($acceptedAdrs as $adrPath) {
        expect((string) file_get_contents($adrPath))->toContain('Status: Accepted');
    }

    $architectureText = '';

    foreach (glob($projectRoot.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'02-architecture'.DIRECTORY_SEPARATOR.'*.md') as $document) {
        $architectureText .= (string) file_get_contents($document);
    }

    expect($architectureText)
        ->toContain('LegalEntityScope')
        ->toContain('DATETIME(6)')
        ->toContain('[effective_from, effective_to)')
        ->toContain('BIGINT UNSIGNED')
        ->toContain('DECIMAL(19,4)')
        ->toContain('append-only')
        ->toContain('subject_type');
});

test('local markdown links in phase two documents resolve to files', function () {
    $projectRoot = dirname(__DIR__, 2);
    $documents = array_merge(
        glob($projectRoot.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'02-architecture'.DIRECTORY_SEPARATOR.'*.md'),
        glob($projectRoot.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'adr'.DIRECTORY_SEPARATOR.'*.md'),
    );

    foreach ($documents as $document) {
        $contents = (string) file_get_contents($document);
        preg_match_all('/\[[^\]]+\]\((?!https?:\/\/|#)([^)#]+\.md)(?:#[^)]+)?\)/', $contents, $matches);

        foreach ($matches[1] as $relativeTarget) {
            $target = dirname($document).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeTarget);

            expect(is_file($target))->toBeTrue();
        }
    }
});
