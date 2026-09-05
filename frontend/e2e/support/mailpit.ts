const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8025';

interface MailpitMessageSummary {
    ID: string;
    To: { Address: string }[];
}

interface MailpitMessage {
    Text: string;
}

/**
 * Poll o Mailpit até o e-mail de reset chegar (entrega é assíncrona) e extrai
 * o link de `/reset-password?token=...` do corpo em texto puro.
 */
export async function waitForResetLink(toEmail: string, attempts = 20): Promise<string> {
    for (let attempt = 0; attempt < attempts; attempt++) {
        const list = (await fetch(`${MAILPIT_URL}/api/v1/messages?limit=50`).then((r) => r.json())) as {
            messages: MailpitMessageSummary[];
        };
        const match = list.messages.find((message) => message.To.some((to) => to.Address === toEmail));

        if (match) {
            const full = (await fetch(`${MAILPIT_URL}/api/v1/message/${match.ID}`).then((r) =>
                r.json(),
            )) as MailpitMessage;
            const linkMatch = /https?:\/\/\S*reset-password\?token=\S+/.exec(full.Text);

            if (linkMatch) {
                return linkMatch[0].replace(/[)\]]+$/, '');
            }
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`No password-reset email found for ${toEmail} after ${attempts} attempts.`);
}
