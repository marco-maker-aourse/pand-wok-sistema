import IconoTile from './IconoTile';
import { cx } from './cx';

export default function EmptyState({ icon, title, description, action, className }) {
    return (
        <div className={cx('flex flex-col items-center justify-center px-6 py-12 text-center', className)}>
            {icon && <IconoTile icon={icon} tone="brand" size="lg" className="mb-3 rounded-full" />}
            <p className="text-sm font-medium text-slate-800">{title}</p>
            {description && <p className="mt-1 max-w-md text-sm text-slate-500">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
