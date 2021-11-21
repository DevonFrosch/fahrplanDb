const getButtonIcon = (button) => {
	const icon = button.querySelector(".fas");
	if(icon) {
		return icon;
	}
	button.innerHTML = '<i class="fas fa-spinner"></i> '+button.innerHTML;
	return button.querySelector(".fas");
}
const setButtonProgress = (button, title) => {
	const icon = getButtonIcon(button);
	button.title = title;
	icon.className = "fas fa-spinner fa-spin";
}
const setButtonDone = (button, title) => {
	const icon = getButtonIcon(button);
	button.title = title;
	icon.className = "fas fa-check";
}
const setButtonError = (button, title, additionalInfo) => {
	const icon = getButtonIcon(button);
	if(title === undefined) {
		title = "Unbekannter Fehler";
	}
	if(additionalInfo !== undefined) {
		title += "\n"+additionalInfo;
	}
	button.title = title;
	icon.className = "fas fa-exclamation-triangle";
}
