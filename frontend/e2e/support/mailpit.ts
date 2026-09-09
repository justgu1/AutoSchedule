const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8025';

interface MailpitMessageSummary {
    ID: string;
    To: { Address: string }[];
}

interface MailpitMessage {
    Text: string;
}

/** Poll o Mailpit até achar um e-mail pro destinatário cujo corpo bate com `linkPattern`, e extrai o link. */
async function waitForLink(toEmail: string, linkPattern: RegExp, attempts: number): Promise<string> {
    for (let attempt = 0; attempt < attempts; attempt++) {
        const list = (await fetch(`${MAILPIT_URL}/api/v1/messages?limit=50`).then((r) => r.json())) as {
            messages: MailpitMessageSummary[];
        };
        const match = list.messages.find((message) => message.To.some((to) => to.Address === toEmail));

        if (match) {
            const full = (await fetch(`${MAILPIT_URL}/api/v1/message/${match.ID}`).then((r) =>
                r.json(),
            )) as MailpitMessage;
            const linkMatch = linkPattern.exec(full.Text);

            if (linkMatch) {
                return linkMatch[0].replace(/[)\]]+$/, '');
            }
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`No matching email found for ${toEmail} after ${attempts} attempts.`);
}

export function waitForResetLink(toEmail: string, attempts = 20): Promise<string> {
    return waitForLink(toEmail, /https?:\/\/\S*reset-password\?token=\S+/, attempts);
}

/**
 * O e-mail de confirmação só sai quando a rotina agendada decide que o veículo está livre --
 * até 60s de intervalo entre execuções, por isso o número de tentativas é bem maior aqui.
 */
export function waitForAppointmentConfirmationLink(toEmail: string, attempts = 150): Promise<string> {
    return waitForLink(toEmail, /https?:\/\/\S*\/agendamentos\/\S+\/confirmar\?token=\S+/, attempts);
}
