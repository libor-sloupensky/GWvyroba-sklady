from pathlib import Path
import re
text = Path("views/production_plans.php").read_text()
for match in re.finditer(r"\|\| \)", text):
    start = max(0, match.start() - 40)
    end = match.start() + 20
    print(text[start:end])
