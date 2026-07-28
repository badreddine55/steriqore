import { Head } from '@inertiajs/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import practiceSettings from '@/routes/practice-settings';
import type {
    AlertSettings,
    DigestSubscription,
    DluRule,
    LabelFormatVersion,
    PracticeIdentity,
} from '@/types';
import { AlertsSection } from './alerts-section';
import { DluRuleSection } from './dlu-rule-section';
import { IdentitySection } from './identity-section';
import { LabelFormatSection } from './label-format-section';

export default function PracticeSettingsIndex({
    identity,
    dluRules,
    labelFormat,
    alertSettings,
    digestSubscriptions,
    canManageEvidence,
}: {
    identity: PracticeIdentity;
    dluRules: DluRule[];
    labelFormat: LabelFormatVersion | null;
    alertSettings: AlertSettings;
    digestSubscriptions: DigestSubscription[];
    canManageEvidence: boolean;
}) {
    return (
        <>
            <Head title="Practice settings" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Practice settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Tenant-level configuration — three of these four
                        sections feed directly into printed evidence, so
                        every change here is audited.
                    </p>
                </div>

                <Tabs defaultValue="identity">
                    <TabsList>
                        <TabsTrigger value="identity">Identity</TabsTrigger>
                        <TabsTrigger value="label-format">
                            Label format
                        </TabsTrigger>
                        <TabsTrigger value="dlu-rules">
                            DLU rule table
                        </TabsTrigger>
                        <TabsTrigger value="alerts">
                            Alerts &amp; notifications
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="identity" className="mt-4">
                        <IdentitySection identity={identity} />
                    </TabsContent>
                    <TabsContent value="label-format" className="mt-4">
                        <LabelFormatSection
                            labelFormat={labelFormat}
                            canManage={canManageEvidence}
                        />
                    </TabsContent>
                    <TabsContent value="dlu-rules" className="mt-4">
                        <DluRuleSection
                            dluRules={dluRules}
                            canManage={canManageEvidence}
                        />
                    </TabsContent>
                    <TabsContent value="alerts" className="mt-4">
                        <AlertsSection
                            alertSettings={alertSettings}
                            digestSubscriptions={digestSubscriptions}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

PracticeSettingsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Practice settings', href: practiceSettings.index() },
    ],
};
