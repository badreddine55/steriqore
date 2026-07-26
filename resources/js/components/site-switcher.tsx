import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import site from '@/routes/site';

/** Only rendered when the practice has more than one site — a display
 * filter for screens that support it, not a security boundary (every
 * query is still scoped to the tenant regardless of this value). */
export function SiteSwitcher() {
    const { sites, currentSiteId } = usePage().props;

    if (sites.length <= 1) {
        return null;
    }

    function onChange(value: string) {
        router.post(
            site.switch.url(),
            { site_id: value === 'all' ? null : value },
            { preserveScroll: true },
        );
    }

    return (
        <Select value={currentSiteId ?? 'all'} onValueChange={onChange}>
            <SelectTrigger size="sm" className="h-8 w-auto max-w-48 gap-2">
                <Building2 className="size-4 text-muted-foreground" />
                <SelectValue />
            </SelectTrigger>
            <SelectContent align="end">
                <SelectItem value="all">All sites</SelectItem>
                {sites.map((s) => (
                    <SelectItem key={s.id} value={s.id}>
                        {s.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
