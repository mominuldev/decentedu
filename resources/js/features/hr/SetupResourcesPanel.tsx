import { useState } from 'react';
import { listDesignations, listHrSections, createDesignation, createHrSection } from './api';
import { SetupResource } from './SetupResource';

export function SetupResourcesPanel() {
  const [activeTab, setActiveTab] = useState<'designations' | 'hr-sections'>('designations');

  return (
    <div className="space-y-6">
      {/* Tabs */}
      <div className="flex gap-2 border-b border-border">
        <button
          onClick={() => setActiveTab('designations')}
          className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'designations'
              ? 'border-brand-600 text-brand-700'
              : 'border-transparent text-muted hover:text-fg'
          }`}
        >
          Designations
        </button>
        <button
          onClick={() => setActiveTab('hr-sections')}
          className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'hr-sections'
              ? 'border-brand-600 text-brand-700'
              : 'border-transparent text-muted hover:text-fg'
          }`}
        >
          Departments
        </button>
      </div>

      {activeTab === 'designations' ? (
        <SetupResource
          resource="designations"
          singular="Designation"
          listFn={listDesignations}
          createFn={createDesignation}
          fields={[
            { name: 'name', label: 'Name', type: 'text', required: true },
            { name: 'name_bn', label: 'Name (Bangla)', type: 'text' },
            { name: 'serial', label: 'Serial', type: 'number' },
            { name: 'description', label: 'Description', type: 'textarea' },
          ]}
        />
      ) : (
        <SetupResource
          resource="hr-sections"
          singular="Department"
          listFn={listHrSections}
          createFn={createHrSection}
          fields={[
            { name: 'name', label: 'Name', type: 'text', required: true },
            { name: 'name_bn', label: 'Name (Bangla)', type: 'text' },
            { name: 'serial', label: 'Serial', type: 'number' },
            { name: 'description', label: 'Description', type: 'textarea' },
          ]}
        />
      )}
    </div>
  );
}