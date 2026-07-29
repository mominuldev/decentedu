import { useNavigate } from 'react-router-dom';
import { Card, Button } from '@/components/ui';
import { Settings } from 'lucide-react';

export function SettingsPanel() {
    const navigate = useNavigate();

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-brand-100 p-3 dark:bg-brand-900/30">
                        <Settings size={24} className="text-brand-600 dark:text-brand-400" />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Site Settings</h3>
                        <p className="text-[12.5px] text-muted">Configure your site's global settings, branding, and contact information</p>
                    </div>
                </div>
                <Button onClick={() => navigate('/cms/settings/edit')}>
                    <Settings size={16} className="mr-2" />
                    Manage Settings
                </Button>
            </div>
        </Card>
    );
}