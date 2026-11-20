<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { useNavigation } from '@/composables/useNavigation';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentOrParentUrl } = useCurrentUrl();
const { sections } = useNavigation();
</script>

<template>
    <div class="bg-background">
        <div class="border-b border-sidebar-border/70">
            <div class="mx-auto flex h-16 items-center gap-3 px-4 md:max-w-7xl">
                <!-- Menú móvil -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-9"
                                aria-label="Abrir menú de navegación"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[280px] p-0">
                            <SheetHeader
                                class="border-b border-sidebar-border/70 p-4 text-left"
                            >
                                <SheetTitle class="sr-only">
                                    Menú de navegación
                                </SheetTitle>
                                <Link
                                    href="/dashboard"
                                    class="flex items-center"
                                    aria-label="Ir al panel principal"
                                >
                                    <AppLogo />
                                </Link>
                            </SheetHeader>
                            <nav
                                class="flex flex-1 flex-col gap-3 overflow-y-auto p-3"
                                aria-label="Navegación principal"
                            >
                                <div
                                    v-for="section in sections"
                                    :key="section.label"
                                >
                                    <p
                                        class="px-2 pb-1 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ section.label }}
                                    </p>
                                    <Link
                                        v-for="item in section.items"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        :class="
                                            isCurrentOrParentUrl(item.href)
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-foreground'
                                        "
                                    >
                                        <component
                                            :is="item.icon"
                                            v-if="item.icon"
                                            class="size-4 shrink-0"
                                        />
                                        {{ item.title }}
                                    </Link>
                                </div>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link
                    href="/dashboard"
                    class="flex shrink-0 items-center gap-2"
                    aria-label="Ir al panel principal"
                >
                    <AppLogo />
                </Link>

                <div class="hidden min-w-0 flex-1 items-center pl-4 lg:flex">
                    <Breadcrumbs
                        v-if="breadcrumbs.length > 0"
                        :breadcrumbs="breadcrumbs"
                    />
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <AppearanceToggle />

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-9 rounded-full p-1 focus-within:ring-2 focus-within:ring-ring/50"
                                :aria-label="`Menú de ${auth.user.name}`"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-full bg-primary/10 font-semibold text-primary"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>
