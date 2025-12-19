import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Check, Chrome, Loader2, Shield, X } from 'lucide-react';

interface Props {
    user: {
        name: string;
        email: string;
    };
}

export default function Authorize({ user }: Props) {
    const [isAuthorizing, setIsAuthorizing] = useState(false);
    const [isSuccess, setIsSuccess] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleAuthorize = async () => {
        setIsAuthorizing(true);
        setError(null);

        try {
            const response = await fetch('/extension/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    version: '1.0.0',
                }),
            });

            const data = await response.json();

            if (data.success) {
                // Send token to extension via multiple methods
                sendTokenToExtension(data.token, data.user);
                setIsSuccess(true);

                // Close window after delay
                setTimeout(() => {
                    window.close();
                }, 3000);
            } else {
                setError(data.error || 'Failed to generate token');
            }
        } catch (err) {
            setError('Connection error. Please try again.');
        } finally {
            setIsAuthorizing(false);
        }
    };

    const sendTokenToExtension = (token: string, userData: object) => {
        // Method 1: BroadcastChannel
        try {
            const channel = new BroadcastChannel('delcom_auth');
            channel.postMessage({
                type: 'AUTH_SUCCESS',
                token,
                user: userData,
            });
            channel.close();
        } catch (e) {
            console.log('BroadcastChannel not available');
        }

        // Method 2: sessionStorage
        try {
            sessionStorage.setItem('delcom_auth_token', JSON.stringify({
                token,
                user: userData,
                timestamp: Date.now(),
            }));
        } catch (e) {
            console.log('sessionStorage not available');
        }

        // Method 3: Custom event
        window.dispatchEvent(new CustomEvent('delcom:auth', {
            detail: { token, user: userData },
        }));

        // Method 4: Window object
        (window as any).__DELCOM_AUTH__ = { token, user: userData };
    };

    const handleCancel = () => {
        window.close();
    };

    if (isSuccess) {
        return (
            <>
                <Head title="Extension Connected - Delcom" />
                <div className="min-h-screen bg-gradient-to-br from-indigo-950 via-indigo-900 to-indigo-950 flex items-center justify-center p-4">
                    <div className="bg-white/10 backdrop-blur-lg rounded-2xl p-8 max-w-md w-full text-center border border-white/10">
                        <div className="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-full flex items-center justify-center animate-scale-in">
                            <Check className="w-10 h-10 text-white" />
                        </div>
                        <h1 className="text-2xl font-bold text-white mb-2">Connected!</h1>
                        <p className="text-white/70 mb-4">{user.email}</p>
                        <p className="text-white/80">
                            Your Delcom extension is now connected.<br />
                            This tab will close automatically...
                        </p>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Connect Extension - Delcom" />
            <div className="min-h-screen bg-gradient-to-br from-indigo-950 via-indigo-900 to-indigo-950 flex items-center justify-center p-4">
                <div className="bg-white/10 backdrop-blur-lg rounded-2xl p-8 max-w-md w-full border border-white/10">
                    {/* Header */}
                    <div className="flex items-center gap-4 mb-6">
                        <div className="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <Chrome className="w-8 h-8 text-white" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-white">Delcom Extension</h1>
                            <p className="text-white/60 text-sm">Connect browser extension</p>
                        </div>
                    </div>

                    {/* User Info */}
                    <div className="bg-white/5 rounded-xl p-4 mb-6">
                        <p className="text-white/60 text-sm mb-1">Connecting as</p>
                        <p className="text-white font-medium">{user.name}</p>
                        <p className="text-white/70 text-sm">{user.email}</p>
                    </div>

                    {/* Permissions */}
                    <div className="mb-6">
                        <p className="text-white/80 text-sm font-medium mb-3 flex items-center gap-2">
                            <Shield className="w-4 h-4" />
                            Extension will be able to:
                        </p>
                        <ul className="space-y-2 text-white/70 text-sm">
                            <li className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div>
                                Scan comments on Instagram & YouTube
                            </li>
                            <li className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div>
                                Save comments to your review queue
                            </li>
                            <li className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div>
                                Delete comments you approve for deletion
                            </li>
                            <li className="flex items-center gap-2">
                                <div className="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div>
                                Access your filter settings
                            </li>
                        </ul>
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 mb-4 text-red-400 text-sm">
                            {error}
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex gap-3">
                        <Button
                            variant="outline"
                            className="flex-1 bg-white/5 border-white/10 text-white hover:bg-white/10"
                            onClick={handleCancel}
                            disabled={isAuthorizing}
                        >
                            <X className="w-4 h-4 mr-2" />
                            Cancel
                        </Button>
                        <Button
                            className="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white border-0"
                            onClick={handleAuthorize}
                            disabled={isAuthorizing}
                        >
                            {isAuthorizing ? (
                                <>
                                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                    Connecting...
                                </>
                            ) : (
                                <>
                                    <Check className="w-4 h-4 mr-2" />
                                    Authorize
                                </>
                            )}
                        </Button>
                    </div>

                    {/* Security Note */}
                    <p className="text-white/40 text-xs text-center mt-6">
                        This will create a secure connection between your browser and Delcom.
                        You can revoke access anytime from Settings.
                    </p>
                </div>
            </div>
        </>
    );
}
