import { useEffect } from "react";
import _ from "lodash";
import {
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";

export const useSetCpa = (temp_data, setCpa, cpa, theme_conf) => {
  const compulsoryPersonalAccident =
    temp_data?.addons?.compulsoryPersonalAccident;

  useEffect(() => {
    if (
      !_.isEmpty(compulsoryPersonalAccident) &&
      compulsoryPersonalAccident[0]?.reason
    ) {
      setCpa(false);
    }
    if (
      compulsoryPersonalAccident &&
      !compulsoryPersonalAccident[0]?.reason &&
      !cpa
    ) {
      setCpa(theme_conf?.broker_config?.cpa === "Yes");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.addons]);
};

export const useResetCpaOnSaod = (cpa, temp_data, setCpa, setMultiCpa) => {
  useEffect(() => {
    if (cpa && temp_data?.odOnly) {
      setCpa(false);
      setMultiCpa(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.odOnly]);
};

export const useHandleCpaChanges = (cpaChangesProps) => {
  // prettier-ignore
  const { upd, temp_data, cpa, multiCpa, userData, enquiry_id,dispatch, type, onCpaChange, setOnCpaChange, setCpaFetch } = cpaChangesProps;
  useEffect(() => {
    if (!upd) {
      if (temp_data?.ownerTypeId === 1) {
        let selectedCpa = [];
        let tenureConst = [];
        let data1 = {};
        if (!cpa && !multiCpa) {
          data1 = {
            enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
            addonData: {
              compulsory_personal_accident: [
                {
                  reason:
                    "I have another motor policy with PA owner driver cover in my name",
                },
              ],
            },
          };

          dispatch(SaveAddonsData(data1));
        } else if (cpa) {
          selectedCpa = ["Compulsory Personal Accident"];
          tenureConst = [];
          data1 = {
            onCpaChange,
            enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
            isTenure: tenureConst,
            addonData: {
              compulsory_personal_accident: [
                { name: "Compulsory Personal Accident" },
              ],
            },
          };

          dispatch(
            SaveAddonsData(data1, false, { setCpaFetch, setOnCpaChange })
          );
        } else if (multiCpa) {
          selectedCpa = ["Compulsory Personal Accident"];
          tenureConst = [type === "car" ? 3 : 5];
          data1 = {
            onCpaChange,
            enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
            isTenure: tenureConst,
            addonData: {
              compulsory_personal_accident: [
                {
                  name: "Compulsory Personal Accident",
                  tenure: type === "car" ? 3 : 5,
                },
              ],
            },
          };

          dispatch(
            SaveAddonsData(data1, false, { setCpaFetch, setOnCpaChange })
          );
        }

        let data = {
          selectedCpa: selectedCpa,
          isTenure: tenureConst,
        };
        dispatch(SetaddonsAndOthers(data));
      } else if (temp_data?.ownerTypeId === 2) {
        let data2 = {
          selectedCpa: [],
        };
        dispatch(SetaddonsAndOthers(data2));
        let data1 = {
          enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
          type: "compulsory_personal_accident",
          addonData: {
            compulsory_personal_accident: [
              { reason: "cpa not applicable to company" },
            ],
          },
        };
        dispatch(SaveAddonsData(data1));
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cpa, temp_data?.ownerTypeId, upd, multiCpa]);
};
