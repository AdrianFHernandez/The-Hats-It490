import sendgrid
from sendgrid.helpers.mail import Mail
import os
from dotenv import load_dotenv

load_dotenv()
sendgrid_api=os.getenv("SENDGRID_API_KEY")
#"490hatsit@gmail.com"

def send_email(to, subject, content):
    sg = sendgrid.SendGridAPIClient(sendgrid_api)
    email = Mail(
        from_email=("afh23@njit.com", "Stock Site"),
        to_emails=to,
        subject=subject,
        plain_text_content=content
    )

    try:
        response = sg.send(email)
        return response.status_code
    except Exception as e:
        print(f'Caught exception: {str(e)}')

# Example usage:
# send_email("recipient@example.com", "Test Subject", "This is a test email.")