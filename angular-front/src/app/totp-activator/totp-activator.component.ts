import {Component, OnDestroy} from '@angular/core';
import {MatProgressSpinner} from "@angular/material/progress-spinner";

import {ApiEnableTotpPostRequest, DefaultService} from "../generated/api";
import {Observable} from "rxjs";
import {FormBuilder, ReactiveFormsModule} from "@angular/forms";

@Component({
  selector: 'app-totp-activator',
  standalone: true,
  imports: [
    MatProgressSpinner,
    ReactiveFormsModule
],
  templateUrl: './totp-activator.component.html',
  styleUrl: './totp-activator.component.css'
})
export class TotpActivatorComponent implements OnDestroy {
  loading = true;
  totpAlreadyActivated: boolean | null = null;
  qrCodeUrl: string | null = null;
  errorCheckingIfTotpIsEnabled: boolean | null = null;
  totpActivationForm = this.formBuilder.group({
    totpCode: '',
  })

  constructor(
    private apiClient: DefaultService,
    private formBuilder: FormBuilder,
  ) {
    this.checkTotpAlreadyActivated();
  }

  ngOnDestroy(): void {
    this.releaseQrCodeImage();
  }

  private releaseQrCodeImage() {
    if (this.qrCodeUrl !== null) {
      console.log("revoking qr code image");
      URL.revokeObjectURL(this.qrCodeUrl);
    } else {
      console.log("considered revoking qr code image, but it was not set")
    }
  }

  private checkTotpAlreadyActivated() {
    let obs: Observable<boolean> = this.apiClient.apiHasTotpEnabledGet();
    let self = this;
    obs.subscribe({
      next(totpAlreadyActivated: boolean) {
        self.loading = false;
        self.errorCheckingIfTotpIsEnabled = false;
        // Should never occur... but at runtime it occurs all the time (probably an issue with the generated http client)
        if (typeof totpAlreadyActivated === "string") {
          let bool = self.toBool(totpAlreadyActivated);
          if (bool === undefined) {
            console.error("couldn't cast to bool the value: " + totpAlreadyActivated);
            window.alert("Une erreur a eu lieu. Plus d'infos dans les logs de votre navigateur");
            return;
          }
          self.totpAlreadyActivated = bool;
        } else {
          self.totpAlreadyActivated = totpAlreadyActivated;
        }
      }, error(err) {
        self.loading = false;
        self.errorCheckingIfTotpIsEnabled = true;
        console.log("failed to find out if the user already has totp enabled: " + JSON.stringify(err));
      }
    });
  }

  private toBool(value: string): boolean | undefined {
    try {
      return JSON.parse(value.toLowerCase());
    }
    catch (e) {
      return undefined;
    }
  }

  initiateTotpActivation() {
    this.loading = true;
    let obs = this.apiClient.apiGenerateTotpSecretPost();
    let self = this;
    obs.subscribe({
      next(qrCode) {
        self.loading = false;
        self.qrCodeUrl = URL.createObjectURL(qrCode);
      },
      error(err) {
        self.loading = false;
        window.alert("Failed to get the QR code");
        console.log("Failed to get the QR code: " + JSON.stringify(err));
      }
    })
  }

  submitTotpActivationForm() {
    this.loading = true;
    let payload: ApiEnableTotpPostRequest = {
      totp: this.totpActivationForm.value.totpCode ?? '',
    }
    let self = this;
    let obs = this.apiClient.apiEnableTotpPost(payload);
    obs.subscribe({
      next(result) {
        self.loading = false;
        console.log("totp successfully activated");
        self.totpAlreadyActivated = true;
        self.releaseQrCodeImage();
      }, error(err) {
        self.loading = false;
        console.log("Failed to validate the totp code: " + JSON.stringify(err))
        window.alert("Erreur. Le code soumis était probablement incorrect");
      }
    })

  }
}
