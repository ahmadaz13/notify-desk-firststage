import './bootstrap';
import Alpine from 'alpinejs';
import {
    Activity,
    Bell,
    Briefcase,
    Building,
    ChartColumn,
    ChevronDown,
    Circle,
    ClipboardList,
    Home,
    Landmark,
    Languages,
    LogOut,
    Menu,
    Package,
    Plus,
    Settings,
    Users,
    Wallet,
    Wrench,
    X,
    createIcons,
} from 'lucide';

window.Alpine = Alpine;

Alpine.start();
createIcons({
    icons: {
        Activity,
        Bell,
        Briefcase,
        Building,
        ChartColumn,
        ChevronDown,
        Circle,
        ClipboardList,
        Home,
        Landmark,
        Languages,
        LogOut,
        Menu,
        Package,
        Plus,
        Settings,
        Users,
        Wallet,
        Wrench,
        X,
    },
});
