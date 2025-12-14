import Heading from '@/components/heading';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, Plan } from '@/types';
import { Head } from '@inertiajs/react';
import { Check, Crown, Zap, Building2, Sparkles } from 'lucide-react';

interface Props {
    plans: Plan[];
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

export default function SubscriptionPlans({ plans }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscription Plans" />
            <PageContainer maxWidth="7xl">
                <div className="text-center mb-10">
                    <Heading title="Choose Your Plan" />
                    <p className="text-muted-foreground mt-2 max-w-2xl mx-auto">
                        Select the plan that best fits your comment moderation needs.
                        Upgrade anytime to unlock more platforms and actions.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {plans.map((plan) => (
                        <PlanCard key={plan.id} plan={plan} />
                    ))}
                </div>

                <div className="mt-12 text-center text-muted-foreground text-sm">
                    <p>All plans include 14-day free trial. No credit card required.</p>
                    <p className="mt-1">Need custom solutions? <a href="#" className="underline">Contact us</a></p>
                </div>
            </PageContainer>
        </AppLayout>
    );
}

function PlanCard({ plan }: { plan: Plan }) {
    const isPopular = plan.slug === 'pro';
    const isUnlimited = plan.monthly_action_limit === -1;
    const features = plan.features || [];

    return (
        <Card className={`relative flex flex-col ${isPopular ? 'border-primary shadow-lg' : ''}`}>
            {isPopular && (
                <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary">
                    Most Popular
                </Badge>
            )}

            <CardHeader className="text-center pb-2">
                <div className={`mx-auto rounded-lg p-3 w-fit ${planColors[plan.slug]}`}>
                    {planIcons[plan.slug]}
                </div>
                <CardTitle className="mt-4">{plan.name}</CardTitle>
                <CardDescription className="min-h-[40px]">
                    {plan.description}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex-1">
                <div className="text-center mb-6">
                    <span className="text-4xl font-bold">
                        ${plan.price_monthly}
                    </span>
                    <span className="text-muted-foreground">/month</span>
                    {plan.price_yearly > 0 && (
                        <p className="text-sm text-muted-foreground mt-1">
                            ${plan.price_yearly}/year (save ${(plan.price_monthly * 12 - plan.price_yearly).toFixed(0)})
                        </p>
                    )}
                </div>

                <div className="space-y-3 mb-6">
                    <div className="flex items-center gap-2 text-sm">
                        <Check className="h-4 w-4 text-green-600" />
                        <span>
                            {isUnlimited
                                ? 'Unlimited actions/month'
                                : `${plan.monthly_action_limit.toLocaleString()} actions/month`
                            }
                        </span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <Check className="h-4 w-4 text-green-600" />
                        <span>
                            {plan.max_platforms === -1
                                ? 'Unlimited platforms'
                                : `Up to ${plan.max_platforms} platforms`
                            }
                        </span>
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
                    variant={isPopular ? 'default' : 'outline'}
                >
                    {plan.slug === 'free' ? 'Current Plan' : 'Upgrade Now'}
                </Button>
            </CardFooter>
        </Card>
    );
}
