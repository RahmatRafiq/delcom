import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function TermsOfService() {
    return (
        <>
            <Head title="Terms of Service" />
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
                <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
                    <a
                        href="/"
                        className="mb-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Home
                    </a>

                    <div className="rounded-lg bg-white p-8 shadow dark:bg-gray-800">
                        <h1 className="mb-2 text-3xl font-bold text-gray-900 dark:text-white">Terms of Service</h1>
                        <p className="mb-8 text-sm text-gray-500 dark:text-gray-400">Last updated: December 16, 2025</p>

                        <div className="prose prose-gray dark:prose-invert max-w-none">
                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">1. Acceptance of Terms</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    By accessing or using Delcom ("the Service"), you agree to be bound by these Terms of Service. If you do not agree
                                    to these terms, please do not use the Service.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">2. Description of Service</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Delcom is an automated comment moderation platform that helps content creators filter and manage comments on their
                                    social media channels, including but not limited to YouTube. The Service uses filters and rules you define to
                                    automatically detect and take action on unwanted comments such as spam, hate speech, and scams.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">3. Account Registration</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>You must provide accurate and complete information when creating an account</li>
                                    <li>You are responsible for maintaining the security of your account</li>
                                    <li>You must be at least 18 years old to use the Service</li>
                                    <li>One person or entity may not maintain more than one account</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">4. Platform Authorization</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    To use the Service, you must authorize Delcom to access your social media accounts through OAuth. By doing so,
                                    you:
                                </p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Grant Delcom permission to read comments on your channels</li>
                                    <li>Grant Delcom permission to delete, hide, or moderate comments based on your filters</li>
                                    <li>Confirm that you are the owner or authorized manager of the connected channels</li>
                                    <li>Understand that you can revoke access at any time through your platform settings</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">5. User Responsibilities</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">You agree to:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Only connect channels that you own or have authorization to manage</li>
                                    <li>Create and maintain appropriate filters for your content needs</li>
                                    <li>Review your moderation logs periodically to ensure accuracy</li>
                                    <li>Not use the Service to violate any platform's terms of service</li>
                                    <li>Not use the Service for any illegal or unauthorized purpose</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">6. Acceptable Use</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">You may NOT use Delcom to:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Mass-delete legitimate comments or engage in censorship</li>
                                    <li>Interfere with other users' experience on social platforms</li>
                                    <li>Violate YouTube or other platforms' Community Guidelines</li>
                                    <li>Abuse the API quota or circumvent usage limits</li>
                                    <li>Reverse engineer, decompile, or modify the Service</li>
                                    <li>Share your account credentials with others</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">7. Subscription and Billing</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Some features require a paid subscription</li>
                                    <li>Subscriptions are billed monthly or annually as selected</li>
                                    <li>You may cancel your subscription at any time</li>
                                    <li>Refunds are handled on a case-by-case basis</li>
                                    <li>Usage limits are enforced based on your subscription plan</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">8. Service Limitations</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">You acknowledge that:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>The Service depends on third-party APIs which may have downtime or changes</li>
                                    <li>Automated moderation may occasionally make mistakes (false positives/negatives)</li>
                                    <li>We cannot guarantee 100% accuracy in filtering</li>
                                    <li>Usage is subject to API quota limits imposed by third-party platforms</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">9. Disclaimer of Warranties</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED. WE
                                    DO NOT WARRANT THAT THE SERVICE WILL BE UNINTERRUPTED, ERROR-FREE, OR COMPLETELY SECURE.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">10. Limitation of Liability</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    TO THE MAXIMUM EXTENT PERMITTED BY LAW, DELCOM SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL,
                                    CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING BUT NOT LIMITED TO:
                                </p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Loss of revenue or profits</li>
                                    <li>Loss of data or content</li>
                                    <li>Damage to reputation from incorrectly moderated comments</li>
                                    <li>Service interruptions or API unavailability</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">11. Indemnification</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    You agree to indemnify and hold harmless Delcom from any claims, damages, or expenses arising from your use of the
                                    Service or violation of these Terms.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">12. Termination</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>You may terminate your account at any time from your account settings</li>
                                    <li>We may suspend or terminate accounts that violate these Terms</li>
                                    <li>Upon termination, your data will be deleted as per our Privacy Policy</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">13. Changes to Terms</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    We reserve the right to modify these Terms at any time. We will notify users of significant changes via email or
                                    through the Service. Continued use after changes constitutes acceptance of the new Terms.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">14. Governing Law</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    These Terms shall be governed by and construed in accordance with the laws of Indonesia, without regard to its
                                    conflict of law provisions.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">15. Contact</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">For questions about these Terms, contact us at:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Email: legal@delcom.app</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
