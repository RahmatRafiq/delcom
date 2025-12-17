import Heading from '@/components/heading';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, Plan } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, Check, Crown, Sparkles, Zap } from 'lucide-react';

interface Props {
    plans: Plan[];
    currentPlanSlug: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subscription Plans', href: route('subscription.plans') },
];

const planIcons: Record<string, React.ReactNode> = {
    free: <Zap className="h-6 w-6" />,
    basic: <Sparkles className="h-6 w-6" />,
    pro: <Crown className="h-6 w-6" />,
    enterprise: <Building2 className="h-6 w-6" />,
};

const planColors: Record<string, string> = {
    free: 'text-gray-600 bg-gray-100 dark:bg-gray-800',
    basic: 'text-blue-600 bg-blue-100 dark:bg-blue-900/30',
    pro: 'text-purple-600 bg-purple-100 dark:bg-purple-900/30',
    enterprise: 'text-amber-600 bg-amber-100 dark:bg-amber-900/30',
};

export default function SubscriptionPlans({ plans, currentPlanSlug }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscription Plans" />
            <PageContainer maxWidth="7xl">
                <div className="mb-10 text-center">
                    <Heading title="Choose Your Plan" />
                    <p className="text-muted-foreground mx-auto mt-2 max-w-2xl">
                        Select the plan that best fits your comment moderation needs. Upgrade anytime to unlock more platforms and actions.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {plans.map((plan) => (
                        <PlanCard key={plan.id} plan={plan} currentPlanSlug={currentPlanSlug} />
                    ))}
                </div>

                <div className="text-muted-foreground mt-12 text-center text-sm">
                    <p>All plans include 14-day free trial. No credit card required.</p>
                    <p className="mt-1">
                        Need custom solutions?{' '}
                        <a href="#" className="underline">
                            Contact us
                        </a>
                    </p>
                </div>
            </PageContainer>
        </AppLayout>
    );
}

function PlanCard({ plan, currentPlanSlug }: { plan: Plan; currentPlanSlug: string }) {
    const isPopular = plan.slug === 'pro';
    const isCurrentPlan = plan.slug === currentPlanSlug;
    const isUnlimited = plan.monthly_action_limit === -1;
    const features = plan.features || [];

    return (
        <Card className={`relative flex flex-col ${isCurrentPlan ? 'border-green-500 bg-green-50/50 dark:bg-green-900/10' : isPopular ? 'border-primary shadow-lg' : ''}`}>
            {isCurrentPlan && <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 bg-green-600">Your Plan</Badge>}
            {isPopular && !isCurrentPlan && <Badge className="bg-primary absolute -top-3 left-1/2 -translate-x-1/2">Most Popular</Badge>}

            <CardHeader className="pb-2 text-center">
                <div className={`mx-auto w-fit rounded-lg p-3 ${planColors[plan.slug]}`}>{planIcons[plan.slug]}</div>
                <CardTitle className="mt-4">{plan.name}</CardTitle>
                <CardDescription className="min-h-[40px]">{plan.description}</CardDescription>
            </CardHeader>

            <CardContent className="flex-1">
                <div className="mb-6 text-center">
                    <span className="text-4xl font-bold">${plan.price_monthly}</span>
                    <span className="text-muted-foreground">/month</span>
                    {plan.price_yearly > 0 && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            ${plan.price_yearly}/year (save ${(plan.price_monthly * 12 - plan.price_yearly).toFixed(0)})
                        </p>
                    )}
                </div>

                <div className="mb-6 space-y-3">
                    <div className="flex items-center gap-2 text-sm">
                        <Check className="h-4 w-4 text-green-600" />
                        <span>{isUnlimited ? 'Unlimited actions/month' : `${plan.monthly_action_limit.toLocaleString()} actions/month`}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <Check className="h-4 w-4 text-green-600" />
                        <span>{plan.max_platforms === -1 ? 'Unlimited platforms' : `Up to ${plan.max_platforms} platforms`}</span>
                    </div>
                    {features.map((feature, idx) => (
                        <div key={idx} className="flex items-center gap-2 text-sm">
                            <Check className="h-4 w-4 text-green-600" />
                            <span>{feature}</span>
                        </div>
                    ))}
                </div>
            </CardContent>

            <CardFooter>
                <Button
                    className="w-full"
                    variant={isCurrentPlan ? 'secondary' : isPopular ? 'default' : 'outline'}
                    disabled={isCurrentPlan}
                >
                    {isCurrentPlan ? 'Current Plan' : 'Upgrade Now'}
                </Button>
            </CardFooter>
        </Card>
    );
}
