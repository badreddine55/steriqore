/**
 * Reads real mail out of the Mailpit container the app is already
 * configured to send through (docker-compose.yml: MAIL_HOST=mailpit).
 * This is the only legitimate way for this journey to obtain an
 * invitation's accept token — Invitation.token is deliberately hidden
 * from every API response (App\Domain\Identity\Models\Invitation), so
 * the only place a real invitee ever sees it is the email itself, same
 * as a real user would when they click the link.
 */
const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8035';

type MailpitSearchResult = { messages: Array<{ ID: string }> };
type MailpitMessage = { Text: string; HTML: string };

async function findMessageId(
    toEmail: string,
): Promise<string | null> {
    const response = await fetch(
        `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${toEmail}`)}`,
    );

    if (!response.ok) {
        throw new Error(
            `Mailpit search failed: ${response.status} ${await response.text()}`,
        );
    }

    const body = (await response.json()) as MailpitSearchResult;

    return body.messages[0]?.ID ?? null;
}

/**
 * Polls Mailpit (mail is queued, so delivery isn't instant) until a
 * message to this address shows up, then returns its plain-text body.
 */
export async function waitForMailTo(
    toEmail: string,
    timeoutMs = 20_000,
): Promise<string> {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        const id = await findMessageId(toEmail);

        if (id !== null) {
            const response = await fetch(`${MAILPIT_URL}/api/v1/message/${id}`);

            if (!response.ok) {
                throw new Error(
                    `Mailpit message fetch failed: ${response.status} ${await response.text()}`,
                );
            }

            const message = (await response.json()) as MailpitMessage;

            return message.Text;
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`No mail arrived for ${toEmail} within ${timeoutMs}ms`);
}

/** Extracts the invitation token from InvitationMail's accept link. */
export function extractInvitationToken(mailBody: string): string {
    const match = mailBody.match(/accept-invitation\?token=([^\s&"]+)/);

    if (!match) {
        throw new Error(
            `No invitation token found in mail body:\n${mailBody}`,
        );
    }

    return match[1];
}
