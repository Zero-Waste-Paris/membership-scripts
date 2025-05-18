import {Component, EventEmitter, Output} from '@angular/core';
import { DefaultLoginService } from '../generated/login/api/default.service';
import { FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import {MatProgressSpinner} from "@angular/material/progress-spinner";
import {NgIf} from "@angular/common";
import {Model2faCheckPostRequest, TotpResult} from "../generated/login";
import {Observable} from "rxjs";

@Component({
  selector: 'app-totp-login',
  standalone: true,
  templateUrl: './totp-login.component.html',
  styleUrl: './totp-login.component.css',
  imports: [FormsModule, ReactiveFormsModule, MatProgressSpinner, NgIf]
})
export class TotpLoginComponent {
  @Output() totpSuccessful = new EventEmitter();
  form = this.formBuilder.group({
    totpCode: '',
  });
  formBeingProcessed = false;

  constructor(
    private loginClient: DefaultLoginService,
    private formBuilder: FormBuilder
  ) { }

  onSubmit() {
    let formValues = this.form.value;
    let payload: Model2faCheckPostRequest = {
      _auth_code: formValues.totpCode ?? '',
    };

    let self = this;
    this.formBeingProcessed = true;
    let obs: Observable<TotpResult> = this.loginClient._2faCheckPost(payload);
    obs.subscribe({
      next: (totpResult) => {
        if (totpResult.isSuccessful) {
          console.log("successfully submitted totp code. Now logged in as " + totpResult.login);
          self.totpSuccessful.emit();
        } else {
          self.formBeingProcessed = false;
          const errorMessage = "Incorrect totp code";
          console.log(errorMessage);
          window.alert(errorMessage);
        }

      },
      error(err) {
        self.formBeingProcessed = false;
        console.log("Submission of totp code failed: " + JSON.stringify(err));
        window.alert("Failed to submit totp code");
      }
    })
  }
}
