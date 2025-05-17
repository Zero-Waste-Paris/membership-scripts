import { Component, Output, EventEmitter } from '@angular/core';
import { NgIf } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { DefaultLoginService } from '../generated/login/api/default.service';
import { LoginPostRequest} from '../generated/login/model/loginPostRequest';
import { LoginResult } from '../generated/login/model/loginResult';
import { Observable } from 'rxjs';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css'],
  standalone: true,
  imports: [FormsModule, NgIf, ReactiveFormsModule, MatProgressSpinnerModule]
})
export class LoginComponent {
	@Output() loginSuccessful = new EventEmitter();
	credentialsBeingProcessed = false;

	credentialsForm = this.formBuilder.group({
		username: '',
		password: ''
	});

	constructor(
		private loginClient: DefaultLoginService,
		private formBuilder: FormBuilder
	) {
		this.isLoggedIn();
	}

	isLoggedIn() {
		let obs: Observable<LoginResult> = this.loginClient.loginGet();
		let self = this;
		obs.subscribe({
			next(loginResult) {
        if (loginResult.status === LoginResult.StatusEnum.Success) {
          console.log("already logged in as " + loginResult.login);
          self.loginSuccessful.emit(); // TODO: shall we emit username?
        } else {
          console.log("Not logged in yet (status: " + loginResult.status + ")");
        }
			},
			error(err) {
				console.log("Not logged in yet: " + JSON.stringify(err));
			}
		});
	}

	onSubmit(): void {
		let formValues = this.credentialsForm.value;
		let payload: LoginPostRequest = {
			username: formValues.username ?? '',
			password: formValues.password ?? ''
		};

		let self = this;
		this.credentialsBeingProcessed = true;
		let obs: Observable<LoginResult> = this.loginClient.loginPost(payload);
		obs.subscribe({
			next(loginResult) {
        switch (loginResult.status) {
          case LoginResult.StatusEnum.Success:
            console.log("successfully logged in as " + loginResult.login);
            self.loginSuccessful.emit(); // TODO: shall we emit username?
            break;
          case LoginResult.StatusEnum.FailureCredentials:
            self.credentialsBeingProcessed = false;
            const errorMessage = "Failed to log in: invalid credentials";
            console.log(errorMessage);
            window.alert(errorMessage);
            break;
          case LoginResult.StatusEnum.Missing2Fa:
            // TODO: display form to submit totp
            break;
        }
			},
			error(err) {
				self.credentialsBeingProcessed = false;
				console.log("Failed to log in: " + JSON.stringify(err));
				window.alert("Authentication failed");
			}
		});
	}
}
