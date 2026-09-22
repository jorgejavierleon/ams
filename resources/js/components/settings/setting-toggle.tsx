import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/** A single labelled toggle row, used by the org-wide Settings pages. */
export function SettingToggle({
    id,
    label,
    hint,
    checked,
    onCheckedChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-6 px-5 py-4">
            <div className="space-y-0.5">
                <Label
                    htmlFor={id}
                    className="cursor-pointer text-sm font-medium"
                >
                    {label}
                </Label>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
            />
        </div>
    );
}
