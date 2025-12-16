import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function PrivacyPolicy() {
    return (
        <>
            <Head title="Privacy Policy" />
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
                        <h1 className="mb-2 text-3xl font-bold text-gray-900 dark:text-white">Privacy Policy</h1>
                        <p className="mb-8 text-sm text-gray-500 dark:text-gray-400">Last updated: December 16, 2025</p>

                        <div className="prose prose-gray dark:prose-invert max-w-none">
                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">1. Introduction</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Delcom ("we", "our", or "us") is a comment moderation platform that helps content creators
                                    automatically filter and manage comments on their social media channels. This Privacy Policy
                                    explains how we collect, use, and protect your information when you use our service.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">2. Information We Collect</h2>

                                <h3 className="mb-2 text-lg font-medium text-gray-800 dark:text-gray-200">2.1 Account Information</h3>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Name and email address (from your Google/social login)</li>
                                    <li>Profile picture (optional)</li>
                                    <li>Account preferences and settings</li>
                                </ul>

                                <h3 className="mb-2 text-lg font-medium text-gray-800 dark:text-gray-200">2.2 Platform Connection Data</h3>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>YouTube channel ID and channel name</li>
                                    <li>OAuth access tokens (encrypted and stored securely)</li>
                                    <li>List of videos on your channel (for comment scanning)</li>
                                </ul>

                                <h3 className="mb-2 text-lg font-medium text-gray-800 dark:text-gray-200">2.3 Comment Data</h3>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Comment text (temporarily processed for filter matching)</li>
                                    <li>Commenter username (for moderation logs)</li>
                                    <li>Moderation action taken (delete, hide, flag)</li>
                                </ul>

                                <h3 className="mb-2 text-lg font-medium text-gray-800 dark:text-gray-200">2.4 Usage Data</h3>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Number of comments moderated</li>
                                    <li>Filter patterns you create</li>
                                    <li>App usage statistics</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">3. How We Use Your Information</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li><strong>Provide the Service:</strong> Scan comments on your channels and apply your moderation filters</li>
                                    <li><strong>Moderation Logs:</strong> Keep records of actions taken for your review</li>
                                    <li><strong>Usage Tracking:</strong> Monitor your subscription usage limits</li>
                                    <li><strong>Improve Service:</strong> Analyze usage patterns to improve our product</li>
                                    <li><strong>Communication:</strong> Send service-related notifications</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">4. Data Storage and Security</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>OAuth tokens are encrypted using AES-256 encryption</li>
                                    <li>All data is stored on secure servers with SSL/TLS encryption</li>
                                    <li>We do not store the full content of comments permanently - only metadata for logs</li>
                                    <li>Access to user data is restricted to authorized personnel only</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">5. Data Sharing</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    We do <strong>NOT</strong> sell, rent, or share your personal data with third parties for marketing purposes.
                                </p>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">We may share data only in these cases:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>With your explicit consent</li>
                                    <li>To comply with legal obligations</li>
                                    <li>To protect our rights and prevent fraud</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">6. Third-Party Services</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    We integrate with the following third-party services:
                                </p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li><strong>Google/YouTube API:</strong> To access and moderate comments on your YouTube channel</li>
                                    <li><strong>Stripe:</strong> For payment processing (we do not store credit card information)</li>
                                </ul>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Delcom's use and transfer of information received from Google APIs adheres to the{' '}
                                    <a
                                        href="https://developers.google.com/terms/api-services-user-data-policy"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        Google API Services User Data Policy
                                    </a>
                                    , including the Limited Use requirements.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">7. Your Rights</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">You have the right to:</p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li><strong>Access:</strong> View all data we have about you</li>
                                    <li><strong>Export:</strong> Download your moderation logs</li>
                                    <li><strong>Delete:</strong> Request deletion of your account and all associated data</li>
                                    <li><strong>Revoke Access:</strong> Disconnect platforms and revoke OAuth permissions at any time</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">8. Data Retention</h2>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Account data: Retained while your account is active</li>
                                    <li>Moderation logs: Retained for 90 days, then automatically deleted</li>
                                    <li>OAuth tokens: Deleted immediately when you disconnect a platform</li>
                                    <li>Upon account deletion: All data is permanently deleted within 30 days</li>
                                </ul>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">9. Changes to This Policy</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    We may update this Privacy Policy from time to time. We will notify you of any significant
                                    changes via email or through the app. Continued use of the service after changes constitutes
                                    acceptance of the updated policy.
                                </p>
                            </section>

                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">10. Contact Us</h2>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    If you have questions about this Privacy Policy or your data, contact us at:
                                </p>
                                <ul className="mb-4 list-disc space-y-1 pl-6 text-gray-600 dark:text-gray-300">
                                    <li>Email: privacy@delcom.app</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
