<?php

namespace App\Http\Requests\Business;

/**
 * Create a CompanyMember child record. Authorization (update permission on the parent) and rules live in CompanyMemberRequest.
 */
class StoreCompanyMemberRequest extends CompanyMemberRequest {}
