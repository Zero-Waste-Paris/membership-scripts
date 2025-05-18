import { Component } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { DefaultService } from '../generated/api/api/default.service';
import { ApiUpdateUserPasswordPostRequest } from '../generated/api/model/apiUpdateUserPasswordPostRequest';
import { Router } from '@angular/router';
import {TotpActivatorComponent} from "../totp-activator/totp-activator.component";

@Component({
  selector: 'app-account',
  templateUrl: './account.component.html',
  styleUrls: ['./account.component.css'],
  standalone: true,
  imports: [FormsModule, ReactiveFormsModule, TotpActivatorComponent]
})
export class AccountComponent {
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

	onSubmitNewPasswordForm(): void {
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
