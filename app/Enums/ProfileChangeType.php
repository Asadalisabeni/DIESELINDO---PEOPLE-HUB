<?php

namespace App\Enums;

enum ProfileChangeType: string
{
    case LegalName = 'legal_name';
    case MaritalStatus = 'marital_status';
    case BankAccount = 'bank_account';
    case TaxProfile = 'tax_profile';
    case BpjsProfile = 'bpjs_profile';
    case FamilyData = 'family_data';
    case IdentityDocument = 'identity_document';
    case EmploymentData = 'employment_data';

    public function labelKey(): string
    {
        return 'ess.request_types.'.$this->value;
    }

    public function requiresAttachment(): bool
    {
        return in_array($this, [
            self::LegalName,
            self::MaritalStatus,
            self::BankAccount,
            self::IdentityDocument,
        ], true);
    }

    public function requiresManualFollowUp(): bool
    {
        return $this === self::EmploymentData;
    }
}
