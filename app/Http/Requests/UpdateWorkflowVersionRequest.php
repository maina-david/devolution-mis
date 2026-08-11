<?php

namespace App\Http\Requests;

use App\Models\WorkflowVersion;

class UpdateWorkflowVersionRequest extends StoreWorkflowVersionRequest
{
    public function authorize(): bool
    {
        $workflowVersion = $this->route('workflowVersion');

        return parent::authorize()
            && $workflowVersion instanceof WorkflowVersion
            && $workflowVersion->status === 'draft';
    }
}
