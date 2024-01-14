import { Component } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { DefaultService } from '../generated/api/api/default.service';
import { ApiUpdateUserPasswordPostRequest } from '../generated/api/model/apiUpdateUserPasswordPostRequest';
import { Router } from '@angular/router';

@Component({
	selector: 'app-password-changer',
	templateUrl: './password-changer.component.html',
	styleUrls: ['./password-changer.component.css'],
	standalone: true,
	imports: [FormsModule, ReactiveFormsModule]
})
export class PasswordChangerComponent {
	newPasswordSubmitted = false;

	newPasswordForm = this.formBuilder.group({
		newPassword: '',
		currentPassword: '',
	});

	constructor(
		private apiClient: DefaultService,
		private formBuilder: FormBuilder,
		private router: Router,
	) {}

	onSubmit(): void {
		let formValues = this.newPasswordForm.value;
		let payload: ApiUpdateUserPasswordPostRequest = {
			newPassword: formValues.newPassword ?? '',
			currentPassword: formValues.currentPassword ?? '',
		};

		let self = this;
		this.newPasswordSubmitted = true;
		let obs: Observable<any> = this.apiClient.apiUpdateUserPasswordPost(payload);
		obs.subscribe({
			next() {
				console.log("password successfully updated");
				self.router.navigate(['']);
			},
			error(err) {
				self.newPasswordSubmitted = false;
				let errorMsg = "Failed to update password: " + JSON.stringify(err);
				console.log(errorMsg);
				window.alert(errorMsg);
			}
		});

	}
}
